<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pagamento online: Pix e cartão pelo Mercado Pago e pela Stripe, com a reserva se
 * confirmando sozinha quando o gateway aprova. Os gateways são simulados com Http::fake.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $chale;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setTime(12, 0));

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        // R$ 300 a diária, 10% de desconto no Pix sem prazo.
        $this->chale = Accommodation::factory()->for($this->property)->create(['pix_discount' => 10]);
        $this->owner = User::factory()->create(['property_id' => $this->property->id]);
    }

    private function connect(string $provider = 'mercadopago', array $settings = []): void
    {
        $this->property->update([
            'payment_credentials' => [
                'mercadopago' => ['public_key' => 'TEST-pub', 'access_token' => 'TEST-token', 'webhook_secret' => $settings['mp_secret'] ?? null],
                'stripe' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => $settings['stripe_secret'] ?? null],
            ],
            'settings' => ['payments' => ['pix' => $provider, 'card' => $provider, 'installments' => 3]],
        ]);
    }

    private function request(array $extra = []): TestResponse
    {
        return $this->postJson('/api/properties/pousada/booking-requests', $extra + [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => $this->property->today()->addDays(10)->toDateString(),
            'check_out' => $this->property->today()->addDays(12)->toDateString(),
            'guests' => 2,
            'guest_name' => 'Ana Souza',
            'guest_email' => 'ana@exemplo.com',
            'guest_phone' => '11999990000',
        ]);
    }

    private function checkout(): string
    {
        return $this->request()->assertCreated()->json('checkout.token');
    }

    private function fakeMercadoPago(string $status = 'approved', ?int $amount = 54000): void
    {
        Http::fake([
            'api.mercadopago.com/v1/payments/*' => Http::response(['id' => 777, 'status' => $status, 'transaction_amount' => $amount / 100, 'external_reference' => 'hospedaki-1']),
            'api.mercadopago.com/v1/payments' => Http::response([
                'id' => 777, 'status' => 'pending',
                'point_of_interaction' => ['transaction_data' => ['qr_code' => '00020126PIXCOPIAECOLA', 'ticket_url' => 'https://mpago.la/x']],
            ], 201),
        ]);
    }

    public function test_without_a_gateway_the_request_waits_for_the_owner(): void
    {
        $quote = $this->postJson('/api/properties/pousada/quote', [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => $this->property->today()->addDays(10)->toDateString(),
            'check_out' => $this->property->today()->addDays(12)->toDateString(),
            'guests' => 2,
        ])->assertOk();
        $this->assertSame([], $quote->json('payment.methods'));
        $this->assertSame(0, $quote->json('payment.pix_discount'));

        $this->request()->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('checkout', null);
        Notification::assertSentTo($this->owner, NewBookingRequest::class);
    }

    public function test_with_a_gateway_the_guest_pays_by_pix_and_the_booking_confirms_itself(): void
    {
        $this->connect();
        $this->fakeMercadoPago();

        $quote = $this->postJson('/api/properties/pousada/quote', [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => $this->property->today()->addDays(10)->toDateString(),
            'check_out' => $this->property->today()->addDays(12)->toDateString(),
            'guests' => 2,
        ]);
        $this->assertSame(['pix', 'card'], $quote->json('payment.methods'));
        $this->assertSame([6000, 54000, 10], [$quote->json('payment.pix_discount'), $quote->json('payment.pix_total'), $quote->json('payment.pix_percent')]);

        $response = $this->request()->assertCreated()->assertJsonPath('data.status', 'awaiting_payment');
        $token = $response->json('checkout.token');
        $this->assertSame(40, strlen($token));
        // O dono só fica sabendo quando for pago, e as datas ficam seguras enquanto isso.
        Notification::assertNothingSent();
        $this->request()->assertUnprocessable()->assertJsonValidationErrors('check_in');

        $this->getJson("/api/checkout/{$token}")->assertOk()
            ->assertJsonPath('methods.pix.total', 54000)
            ->assertJsonPath('methods.pix.percent', 10)
            ->assertJsonPath('methods.card.total', 60000)
            ->assertJsonPath('methods.card.installments', 3)
            ->assertJsonPath('methods.card.public_key', 'TEST-pub');

        $this->postJson("/api/checkout/{$token}/pix")->assertOk()
            ->assertJsonPath('payment.qr_code', '00020126PIXCOPIAECOLA')
            ->assertJsonPath('payment.amount', 54000);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.mercadopago.com/v1/payments'
            && $request['payment_method_id'] === 'pix'
            && $request['transaction_amount'] == 540
            && $request['payer']['email'] === 'ana@exemplo.com'
            && $request->hasHeader('X-Idempotency-Key', 'hospedaki-1'));

        // Gerar de novo devolve o mesmo Pix, sem outra cobrança.
        $this->postJson("/api/checkout/{$token}/pix")->assertOk();
        $this->assertSame(1, Payment::count());

        $this->postJson('/api/webhooks/mercadopago/pousada', ['type' => 'payment', 'data' => ['id' => '777']])->assertOk();

        $booking = Booking::first();
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame([54000, 6000, 'pix'], [$booking->total, $booking->pix_discount, $booking->payment_method]);
        $this->assertSame(54000, (int) DB::table('booking_accommodation')->value('subtotal'));
        $this->assertNotNull($booking->paid_at);
        Notification::assertSentTo($this->owner, NewBookingRequest::class);

        $this->getJson("/api/checkout/{$token}")->assertJsonPath('status', 'confirmed')->assertJsonPath('payment.status', 'paid')->assertJsonPath('payment.qr_code', null);
        $this->postJson("/api/checkout/{$token}/pix")->assertUnprocessable()->assertJsonPath('errors.payment.0', 'Esta reserva já está paga e confirmada.');
    }

    public function test_a_paid_notice_with_another_amount_does_not_confirm(): void
    {
        $this->connect();
        $this->fakeMercadoPago('approved', 100);
        $token = $this->checkout();
        $this->postJson("/api/checkout/{$token}/pix")->assertOk();

        $this->postJson('/api/webhooks/mercadopago/pousada', ['type' => 'payment', 'data' => ['id' => '777']])->assertOk();

        $this->assertSame(BookingStatus::AwaitingPayment, Booking::first()->status);
    }

    public function test_mercado_pago_signature_is_checked_when_the_secret_is_saved(): void
    {
        $this->connect(settings: ['mp_secret' => 'segredo']);
        $this->fakeMercadoPago();
        $token = $this->checkout();
        $this->postJson("/api/checkout/{$token}/pix")->assertOk();

        $this->postJson('/api/webhooks/mercadopago/pousada', ['type' => 'payment', 'data' => ['id' => '777']], ['x-signature' => 'ts=1,v1=errado', 'x-request-id' => 'abc'])
            ->assertStatus(400);
        $this->assertSame(BookingStatus::AwaitingPayment, Booking::first()->status);

        $hash = hash_hmac('sha256', 'id:777;request-id:abc;ts:1700;', 'segredo');
        $this->postJson('/api/webhooks/mercadopago/pousada?type=payment&data.id=777', [], ['x-signature' => "ts=1700,v1={$hash}", 'x-request-id' => 'abc'])
            ->assertOk();
        $this->assertSame(BookingStatus::Confirmed, Booking::first()->status);
    }

    public function test_the_checkout_status_asks_the_gateway_when_the_notice_does_not_come(): void
    {
        $this->connect();
        $this->fakeMercadoPago();
        $token = $this->checkout();
        $this->postJson("/api/checkout/{$token}/pix")->assertOk();

        $this->getJson("/api/checkout/{$token}?refresh=1")->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_unpaid_bookings_expire_and_free_the_dates(): void
    {
        $this->connect();
        $token = $this->checkout();

        $this->travel(31)->minutes();

        // Antes de alguém olhar o checkout, as datas já não estão seguras.
        $this->request()->assertCreated()->assertJsonPath('data.status', 'awaiting_payment');
        $this->getJson("/api/checkout/{$token}")->assertOk()->assertJsonPath('status', 'expired')->assertJsonPath('methods.pix', null);
        $this->postJson("/api/checkout/{$token}/pix")->assertUnprocessable()->assertJsonValidationErrors('payment');
    }

    public function test_a_late_payment_confirms_when_the_dates_are_still_free_or_asks_for_a_refund(): void
    {
        $this->connect();
        $this->fakeMercadoPago();
        $token = $this->checkout();
        $this->postJson("/api/checkout/{$token}/pix")->assertOk();
        $booking = Booking::first();
        $booking->update(['status' => BookingStatus::Expired]);

        $this->postJson('/api/webhooks/mercadopago/pousada', ['type' => 'payment', 'data' => ['id' => '777']])->assertOk();
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);

        // Agora outro hóspede pegou as datas antes de o pagamento atrasado chegar.
        Payment::query()->update(['status' => Payment::PENDING]);
        Booking::whereKey($booking->id)->update(['status' => BookingStatus::Expired]);
        $this->property->bookings()->create([
            'status' => BookingStatus::Confirmed, 'check_in' => $booking->check_in, 'check_out' => $booking->check_out, 'guests' => 2,
            'guest_name' => 'Outro', 'guest_phone' => '11988887777', 'total' => 60000,
        ])->accommodations()->attach($this->chale->id, ['subtotal' => 60000]);

        $this->postJson('/api/webhooks/mercadopago/pousada', ['type' => 'payment', 'data' => ['id' => '777']])->assertOk();
        $this->assertSame(BookingStatus::Expired, $booking->fresh()->status);
        $this->assertTrue(Payment::first()->data['refund_needed']);
    }

    public function test_card_on_mercado_pago_is_charged_with_the_sdk_token(): void
    {
        $this->connect();
        Http::fake(['api.mercadopago.com/v1/payments' => Http::sequence()
            ->push(['id' => 1, 'status' => 'rejected', 'status_detail' => 'cc_rejected_insufficient_amount', 'payment_method_id' => 'visa'], 201)
            ->push(['id' => 2, 'status' => 'approved', 'payment_method_id' => 'visa', 'installments' => 3, 'card' => ['last_four_digits' => '4242']], 201)]);
        $token = $this->checkout();
        $card = ['email' => 'ana@exemplo.com', 'token' => 'card-token', 'payment_method_id' => 'visa', 'issuer_id' => '25', 'installments' => 3, 'identification_number' => '123.456.789-09'];

        $this->postJson("/api/checkout/{$token}/card", ['installments' => 4] + $card)->assertJsonValidationErrors('installments');
        $this->postJson("/api/checkout/{$token}/card", $card)->assertOk()
            ->assertJsonPath('status', 'awaiting_payment')
            ->assertJsonPath('payment.status', 'failed')
            ->assertJsonPath('payment.message', 'O cartão não tem limite suficiente.');

        $this->postJson("/api/checkout/{$token}/card", $card)->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('payment.last4', '4242');

        Http::assertSent(fn ($request) => $request['token'] === 'card-token' && $request['transaction_amount'] == 600
            && $request['issuer_id'] === 25 && $request['payer']['identification']['number'] === '12345678909');
        $this->assertSame([60000, 0, 'card'], [Booking::first()->total, Booking::first()->pix_discount, Booking::first()->payment_method]);
    }

    public function test_stripe_pix_and_signed_webhook(): void
    {
        $this->connect('stripe', ['stripe_secret' => 'whsec_x']);
        Http::fake([
            'api.stripe.com/v1/payment_intents/*' => Http::response(['id' => 'pi_1', 'status' => 'succeeded', 'amount' => 54000, 'metadata' => ['payment' => 'hospedaki-1']]),
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_1', 'status' => 'requires_action',
                'next_action' => ['pix_display_qr_code' => ['data' => '000201STRIPEPIX', 'expires_at' => now()->addMinutes(30)->timestamp, 'hosted_instructions_url' => 'https://pay.stripe.com/x']],
            ]),
        ]);
        $token = $this->checkout();

        $this->postJson("/api/checkout/{$token}/pix")->assertOk()->assertJsonPath('payment.qr_code', '000201STRIPEPIX')->assertJsonPath('payment.provider', 'stripe');
        Http::assertSent(fn ($request) => str_contains($request->body(), 'payment_method_types%5B0%5D=pix') && $request['amount'] === 54000 && $request['confirm'] === 'true');

        $payload = json_encode(['type' => 'payment_intent.succeeded', 'data' => ['object' => ['object' => 'payment_intent', 'id' => 'pi_1']]]);
        $signature = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$payload, 'whsec_x');

        $this->call('POST', '/api/webhooks/stripe/pousada', server: ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=bad', 'CONTENT_TYPE' => 'application/json'], content: $payload)->assertStatus(400);
        $this->call('POST', '/api/webhooks/stripe/pousada', server: ['HTTP_STRIPE_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'], content: $payload)->assertOk();

        $this->assertSame(BookingStatus::Confirmed, Booking::first()->status);
    }

    public function test_stripe_card_returns_the_client_secret_for_the_browser(): void
    {
        $this->connect('stripe');
        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_2', 'status' => 'requires_payment_method', 'client_secret' => 'pi_2_secret'])]);
        $token = $this->checkout();

        $this->postJson("/api/checkout/{$token}/card", ['email' => 'ana@exemplo.com', 'token' => 'x'])->assertJsonValidationErrors('token');
        $this->postJson("/api/checkout/{$token}/card", ['email' => 'ana@exemplo.com'])->assertOk()
            ->assertJsonPath('payment.client_secret', 'pi_2_secret')
            ->assertJsonPath('payment.status', 'pending')
            ->assertJsonPath('methods.card.installments', 1);
    }

    public function test_accommodation_choices_and_promo_timer(): void
    {
        $this->connect();
        $this->chale->update(['accepts_card' => false, 'promo_ends_at' => now()->addHours(5)]);

        $site = fn () => collect($this->getJson('/api/properties/pousada')->json('data.accommodations'))->firstWhere('id', $this->chale->id)['payment'];
        $this->assertSame(['pix'], $site()['methods']);
        $this->assertSame(10, $site()['pix_discount']);
        $this->assertSame(now()->addHours(5)->toAtomString(), $site()['promo_ends_at']);
        $this->assertNull($site()['promo_loop_hours']);

        // Passou o prazo: sem loop o desconto acaba; com loop, recomeça a cada 24 horas.
        $this->travel(6)->hours();
        $this->assertSame(0, $site()['pix_discount']);

        $this->chale->update(['promo_loop' => true, 'promo_loop_hours' => 24]);
        $this->assertSame(10, $site()['pix_discount']);
        $this->assertSame(now()->subHour()->addHours(24)->toAtomString(), $site()['promo_ends_at']);
        $this->assertSame(24, $site()['promo_loop_hours']);
    }

    public function test_owner_connects_a_gateway_and_the_secret_never_comes_back(): void
    {
        Sanctum::actingAs($this->owner);
        Http::fake(['api.mercadopago.com/users/me' => Http::sequence()->push(['message' => 'unauthorized'], 401)->whenEmpty(Http::response(['id' => 1]))]);
        $keys = ['public_key' => 'APP_USR-abc-123', 'access_token' => 'APP_USR-9999-secret-7777'];

        $this->putJson('/api/admin/payments/gateways/mercadopago', ['public_key' => 'APP_USR-x', 'access_token' => 'TEST-1'])
            ->assertJsonValidationErrors('access_token');
        $this->putJson('/api/admin/payments/gateways/mercadopago', $keys)->assertJsonValidationErrors('access_token');

        $response = $this->putJson('/api/admin/payments/gateways/mercadopago', $keys)->assertOk()
            ->assertJsonPath('gateways.mercadopago.configured', true)
            ->assertJsonPath('gateways.mercadopago.access_token_hint', '•••• 7777')
            ->assertJsonPath('pix', 'mercadopago')
            ->assertJsonPath('card', 'mercadopago');
        $this->assertStringNotContainsString('9999', $response->getContent());
        $this->assertStringNotContainsString('9999', (string) DB::table('properties')->value('payment_credentials'));

        // Trocar só a chave pública mantém o token salvo.
        $this->putJson('/api/admin/payments/gateways/mercadopago', ['public_key' => 'APP_USR-new'])->assertOk();
        $this->assertSame('APP_USR-9999-secret-7777', $this->property->fresh()->payment_credentials['mercadopago']['access_token']);

        $this->putJson('/api/admin/payments/settings', ['pix' => 'stripe', 'card' => null, 'installments' => 6])->assertJsonValidationErrors('pix');
        $this->putJson('/api/admin/payments/settings', ['pix' => 'mercadopago', 'card' => null, 'installments' => 6])->assertOk()->assertJsonPath('card', null);

        $this->deleteJson('/api/admin/payments/gateways/mercadopago')->assertOk()->assertJsonPath('pix', null)->assertJsonPath('configured', []);
    }

    public function test_demo_simulates_payments_and_blocks_real_keys(): void
    {
        config(['demo.enabled' => true, 'demo.property_slug' => 'pousada']);
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/admin/payments/gateways/stripe', ['publishable_key' => 'pk_live_1', 'secret_key' => 'sk_live_1'])->assertJsonValidationErrors('gateway');
        $this->getJson('/api/admin/payments')->assertJsonPath('pix', 'simulated')->assertJsonPath('demo', true);
        $this->app['auth']->forgetGuards();

        $token = $this->checkout();
        $this->postJson("/api/checkout/{$token}/simulate")->assertJsonValidationErrors('payment');
        $this->postJson("/api/checkout/{$token}/pix")->assertOk()->assertJsonPath('payment.simulated', true);
        $this->postJson("/api/checkout/{$token}/simulate")->assertOk()->assertJsonPath('status', 'confirmed');

        $second = $this->request(['check_in' => $this->property->today()->addDays(20)->toDateString(), 'check_out' => $this->property->today()->addDays(21)->toDateString()])->json('checkout.token');
        $this->postJson("/api/checkout/{$second}/card", ['email' => 'a@b.com', 'last4' => '0002'])->assertJsonPath('payment.status', 'failed');
        $this->postJson("/api/checkout/{$second}/card", ['email' => 'a@b.com', 'last4' => '4242', 'installments' => 1])->assertJsonPath('status', 'confirmed');
    }
}
