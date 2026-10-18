<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    private Accommodation $chale;

    private Accommodation $espaco;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::factory()->create(['slug' => 'pousada']);
        $this->chale = Accommodation::factory()->for($this->property)->create(['base_price' => 30000, 'max_guests' => 2]);
        $this->espaco = Accommodation::factory()->for($this->property)->create(['base_price' => 50000, 'max_guests' => 6]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'accommodation_ids' => [$this->chale->id],
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => 2,
            'guest_name' => 'Maria',
            'guest_phone' => '11999999999',
        ];
    }

    public function test_public_property_page(): void
    {
        $this->property->update([
            'tagline' => 'Desacelere',
            'highlights' => [['title' => 'Silêncio', 'text' => 'Só natureza.']],
            'story' => [['time' => '8h', 'period' => 'Manhã', 'title' => 'Café', 'text' => 'Com vista.']],
        ]);
        $this->property->photos()->create(['path' => 'https://exemplo.com/capa.jpg', 'featured' => true]);
        $this->chale->update(['amenities' => ['Hidromassagem', 'Varanda']]);

        $this->getJson('/api/properties/pousada')
            ->assertOk()
            ->assertJsonCount(2, 'data.accommodations')
            ->assertJsonPath('data.tagline', 'Desacelere')
            ->assertJsonPath('data.highlights.0.title', 'Silêncio')
            ->assertJsonPath('data.story.0.period', 'Manhã')
            ->assertJsonPath('data.photos.0.url', 'https://exemplo.com/capa.jpg')
            ->assertJsonPath('data.photos.0.featured', true)
            ->assertJsonPath('data.accommodations.0.amenities', ['Hidromassagem', 'Varanda'])
            ->assertJsonPath('data.accommodations.1.amenities', []);

        $this->getJson('/api/properties/nao-existe')->assertNotFound();
    }

    public function test_guest_can_request_both_accommodations_together(): void
    {
        $response = $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'accommodation_ids' => [$this->chale->id, $this->espaco->id],
            'guests' => 6,
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 2 * 30000 + 2 * 50000)
            ->assertJsonMissingPath('data.guest_phone');
    }

    public function test_pending_request_holds_dates_and_calendars_are_separate(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_in');

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'accommodation_ids' => [$this->espaco->id],
        ]))->assertCreated();

        // Check-out no mesmo dia do check-in de outra reserva é permitido.
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'check_in' => now()->addDays(12)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
        ]))->assertCreated();
    }

    public function test_blocked_dates_show_in_calendar_and_prevent_booking(): void
    {
        $day = now()->addDays(11)->toDateString();
        $this->chale->blockedDates()->create(['starts_on' => $day, 'ends_on' => $day]);

        $this->getJson('/api/properties/pousada/availability?from='.now()->toDateString().'&to='.now()->addDays(30)->toDateString())
            ->assertOk()
            ->assertJsonPath('accommodations.0.unavailable', [$day]);

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertUnprocessable();
    }

    public function test_owner_confirms_only_own_bookings(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();
        $booking = Booking::first();

        $other = User::factory()->create(['property_id' => Property::factory()->create()->id]);
        Sanctum::actingAs($other);
        $this->postJson("/api/admin/bookings/{$booking->id}/confirm")->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $this->postJson("/api/admin/bookings/{$booking->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.guest_phone', '11999999999');
    }

    public function test_declining_frees_the_dates(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();

        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $this->postJson('/api/admin/bookings/'.Booking::first()->id.'/decline')->assertJsonPath('data.status', 'declined');

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();
    }

    public function test_owner_is_notified_of_new_request(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['property_id' => $this->property->id]);
        $stranger = User::factory()->create(['property_id' => Property::factory()->create()->id]);

        $this->postJson('/api/properties/pousada/booking-requests', $this->payload())->assertCreated();

        Notification::assertSentTo($owner, NewBookingRequest::class);
        Notification::assertNotSentTo($stranger, NewBookingRequest::class);
    }

    public function test_notification_email_renders(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload(['notes' => 'Chego tarde']))->assertCreated();
        $owner = User::factory()->create(['property_id' => $this->property->id]);

        $html = (string) (new NewBookingRequest(Booking::first()))->toMail($owner)->render();

        $this->assertStringContainsString('Maria', $html);
        $this->assertStringContainsString('Chego tarde', $html);
    }

    public function test_owner_login_and_me(): void
    {
        User::factory()->create(['property_id' => $this->property->id, 'email' => 'dono@x.com', 'password' => 'segredo123']);

        $this->postJson('/api/admin/login', ['email' => 'dono@x.com', 'password' => 'errada'])->assertUnprocessable();

        $token = $this->postJson('/api/admin/login', ['email' => 'dono@x.com', 'password' => 'segredo123'])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('property.slug', 'pousada')
            ->assertJsonCount(2, 'property.accommodations');
    }

    public function test_today_is_the_property_timezone_not_utc(): void
    {
        // Sexta, 23/10 às 21h30 em Brasília: em UTC já é sábado, 24/10.
        $this->travelTo(CarbonImmutable::parse('2026-10-24 00:30', 'UTC'));
        $this->assertSame('America/Sao_Paulo', $this->property->refresh()->timezone);

        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_in' => '2026-10-23', 'check_out' => '2026-10-25']))
            ->assertOk();

        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_in' => '2026-10-22', 'check_out' => '2026-10-25']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.check_in.0', 'A data de entrada não pode ser no passado.');

        // No painel, quem sai hoje (no fuso da pousada) ainda aparece em "próximas".
        $base = ['status' => 'confirmed', 'guests' => 2, 'guest_phone' => '11988887777', 'total' => 60000];
        $this->property->bookings()->create(['check_in' => '2026-10-21', 'check_out' => '2026-10-23', 'guest_name' => 'Sai hoje'] + $base);
        $this->property->bookings()->create(['check_in' => '2026-10-20', 'check_out' => '2026-10-22', 'guest_name' => 'Saiu ontem'] + $base);

        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $this->getJson('/api/admin/bookings?status=confirmed&upcoming=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.guest_name', 'Sai hoje');
    }

    public function test_panel_lists_every_upcoming_booking(): void
    {
        foreach (range(1, 35) as $i) {
            $this->property->bookings()->create([
                'status' => 'confirmed', 'guests' => 2, 'guest_name' => "Hóspede {$i}", 'guest_phone' => '11988887777', 'total' => 30000,
                'check_in' => now()->addDays($i * 2)->toDateString(), 'check_out' => now()->addDays($i * 2 + 1)->toDateString(),
            ]);
        }

        Sanctum::actingAs(User::factory()->create(['property_id' => $this->property->id]));
        $this->getJson('/api/admin/bookings?status=confirmed&upcoming=1')->assertOk()->assertJsonCount(35, 'data');
    }

    public function test_guest_text_is_not_markdown_in_the_owner_email(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload([
            'guest_name' => '[Confirme seu acesso](https://evil.example/login)',
            'notes' => '**urgente** _agora_ `código` <b>oi</b>',
        ]))->assertCreated();
        $owner = User::factory()->create(['property_id' => $this->property->id]);

        $html = (string) (new NewBookingRequest(Booking::first()))->toMail($owner)->render();

        $this->assertStringNotContainsString('evil.example/login"', $html);
        $this->assertStringContainsString('[Confirme seu acesso](https://evil.example/login)', $html);
        $this->assertStringNotContainsString('<strong>urgente</strong>', $html);
        $this->assertStringNotContainsString('<em>agora</em>', $html);
        $this->assertStringNotContainsString('<code>', $html);
        $this->assertStringNotContainsString('<b>oi</b>', $html);
        $this->assertStringContainsString('**urgente** _agora_ `código` &lt;b&gt;oi&lt;/b&gt;', $html);
        // Textos fixos do modelo de e-mail do Laravel também saem em português.
        $this->assertStringContainsString('Até mais,', $html);
        $this->assertStringNotContainsString('Regards', $html);
    }

    public function test_api_errors_are_in_portuguese(): void
    {
        $this->postJson('/api/properties/pousada/booking-requests', $this->payload(['guests' => null, 'guest_email' => 'nao-e-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.guests.0', 'O campo hóspedes é obrigatório.')
            ->assertJsonPath('errors.guest_email.0', 'O campo e-mail deve ser um e-mail válido.')
            ->assertJsonPath('message', 'O campo hóspedes é obrigatório. (e mais 1 erro)');

        $this->postJson('/api/properties/pousada/quote', $this->payload(['check_out' => now()->addDays(9)->toDateString()]))
            ->assertJsonPath('errors.check_out.0', 'A data de saída deve ser depois da data de entrada.');

        $this->postJson('/api/admin/login', ['email' => 'x'])
            ->assertJsonPath('errors.email.0', 'O campo e-mail deve ser um e-mail válido.')
            ->assertJsonPath('errors.password.0', 'O campo senha é obrigatório.');

        $this->getJson('/api/properties/nao-existe')->assertNotFound()->assertJsonPath('message', 'Não encontrado.');
        $this->getJson('/api/admin/me')->assertUnauthorized()->assertJsonPath('message', 'Sua sessão expirou. Entre de novo.');

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/properties/pousada/booking-requests', []);
        }
        $this->postJson('/api/properties/pousada/booking-requests', [])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.');
    }

    public function test_panel_tokens_expire_after_30_days(): void
    {
        User::factory()->create(['property_id' => $this->property->id, 'email' => 'dono@x.com', 'password' => 'segredo123']);
        $token = $this->postJson('/api/admin/login', ['email' => 'dono@x.com', 'password' => 'segredo123'])->json('token');

        $this->travel(29)->days();
        $this->withToken($token)->getJson('/api/admin/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->travel(2)->days();
        $this->withToken($token)->getJson('/api/admin/me')->assertUnauthorized();
    }
}
