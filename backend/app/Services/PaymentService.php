<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Http\Resources\PhotoUrl;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Property;
use App\Notifications\NewBookingRequest;
use App\Payments\Gateway;
use App\Payments\GatewayException;
use App\Payments\MercadoPagoGateway;
use App\Payments\SimulatedGateway;
use App\Payments\StripeGateway;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pagamento online das reservas, sempre por um gateway (Mercado Pago ou Stripe).
 *
 * Com um gateway configurado e a acomodação aceitando Pix ou cartão, o pedido nasce
 * "aguardando pagamento" e segura as datas por HOLD_MINUTES. O hóspede paga no checkout do
 * site; o gateway avisa (webhook) ou o checkout pergunta, e a reserva se confirma sozinha.
 * Sem pagar no prazo, ela expira e as datas voltam a ficar livres.
 *
 * Nunca confiamos no aviso do gateway sozinho: o status sempre vem de uma consulta à API com
 * as chaves da pousada, e o valor pago tem que bater com o cobrado.
 */
class PaymentService
{
    public const PROVIDERS = ['mercadopago', 'stripe'];

    /** Gateway de mentira da demonstração. */
    public const SIMULATED = 'simulated';

    public const METHODS = [Payment::PIX, Payment::CARD];

    /** Prazo para pagar depois de fazer o pedido. */
    public const HOLD_MINUTES = 30;

    /** Um Pix novo vale pelo menos isso, mesmo gerado perto do fim do prazo... */
    public const PIX_MIN_MINUTES = 10;

    /** ...mas um pedido nunca segura as datas por mais que isso. */
    public const MAX_HOLD_MINUTES = 90;

    public function __construct(private readonly AvailabilityService $availability) {}

    // ── Configuração ──

    /**
     * Gateways com as chaves completas (na demonstração, só o simulado).
     *
     * @return list<string>
     */
    public function configured(Property $property): array
    {
        if ($this->isDemo($property)) {
            return [self::SIMULATED];
        }

        $credentials = $this->credentials($property);

        return array_values(array_filter(self::PROVIDERS, fn (string $provider) => self::complete($provider, $credentials[$provider] ?? [])));
    }

    /**
     * Qual gateway cobra o Pix e qual cobra o cartão (null = não aceita) e o máximo de parcelas.
     *
     * @return array{pix: ?string, card: ?string, installments: int}
     */
    public function settings(Property $property): array
    {
        $saved = ($property->settings ?? [])['payments'] ?? null;
        $configured = $this->configured($property);
        // Na demonstração, Pix e cartão começam ligados (simulados) até o dono mexer.
        $pick = fn (string $method) => $saved === null && $this->isDemo($property)
            ? self::SIMULATED
            : (in_array($saved[$method] ?? null, $configured, true) ? $saved[$method] : null);

        return [
            'pix' => $pick(Payment::PIX),
            'card' => $pick(Payment::CARD),
            'installments' => max(1, min(12, (int) ($saved['installments'] ?? 1))),
        ];
    }

    /**
     * Formas de pagamento online para estas acomodações: as que a pousada cobra por um
     * gateway e que todas elas aceitam. Vazio = pedido sem pagamento (o dono confirma).
     *
     * @param  iterable<Accommodation>  $accommodations
     * @return list<string>
     */
    public function methodsFor(Property $property, iterable $accommodations): array
    {
        $settings = $this->settings($property);
        $accommodations = collect($accommodations);

        if ($accommodations->isEmpty()) {
            return [];
        }

        return array_values(array_filter([
            $settings['pix'] && $accommodations->every(fn (Accommodation $a) => $a->accepts_pix) ? Payment::PIX : null,
            $settings['card'] && $accommodations->every(fn (Accommodation $a) => $a->accepts_card) ? Payment::CARD : null,
        ]));
    }

    /**
     * Desconto do Pix de cada acomodação sobre o subtotal dela, com a promoção valendo agora.
     *
     * @param  iterable<Accommodation>  $accommodations
     * @param  array<int, int>  $subtotals  id da acomodação => subtotal (centavos)
     * @return array<int, int> id => desconto (centavos)
     */
    public function pixDiscounts(iterable $accommodations, array $subtotals): array
    {
        $discounts = [];
        foreach ($accommodations as $accommodation) {
            $promo = $accommodation->pixPromo();
            if ($promo && isset($subtotals[$accommodation->id])) {
                $discounts[$accommodation->id] = intdiv($subtotals[$accommodation->id] * $promo['percent'], 100);
            }
        }

        return array_filter($discounts);
    }

    /**
     * O pedaço "payment" do orçamento: como vai ser pago e quanto fica no Pix.
     *
     * @param  iterable<Accommodation>  $accommodations
     * @param  array<int, int>  $subtotals
     * @return array{methods: list<string>, pix_discount: int, pix_total: int, pix_percent: int}
     */
    public function quoteSummary(Property $property, iterable $accommodations, array $subtotals): array
    {
        $methods = $this->methodsFor($property, $accommodations);
        $total = array_sum($subtotals);
        $discount = in_array(Payment::PIX, $methods, true) ? array_sum($this->pixDiscounts($accommodations, $subtotals)) : 0;

        return [
            'methods' => $methods,
            'pix_discount' => $discount,
            'pix_total' => $total - $discount,
            'pix_percent' => $total > 0 ? (int) round($discount / $total * 100) : 0,
        ];
    }

    public function gateway(Property $property, string $provider): Gateway
    {
        return $this->make($property, $provider, $this->credentials($property)[$provider] ?? []);
    }

    /**
     * Gateway com estas chaves (o painel testa as chaves novas antes de salvar).
     *
     * @param  array<string, ?string>  $credentials
     */
    public function make(Property $property, string $provider, array $credentials): Gateway
    {
        return match ($provider) {
            'mercadopago' => new MercadoPagoGateway($credentials, $this->notificationUrl($property, $provider)),
            'stripe' => new StripeGateway($credentials),
            self::SIMULATED => $this->isDemo($property) ? new SimulatedGateway : throw new GatewayException('Pagamento simulado só na demonstração.'),
            default => throw new GatewayException('Gateway desconhecido.'),
        };
    }

    /**
     * Endereço que o gateway chama ao mudar um pagamento. O Mercado Pago só aceita https; em
     * desenvolvimento (http) fica sem, e o checkout pergunta a situação sozinho.
     */
    public function notificationUrl(Property $property, string $provider): ?string
    {
        $url = url("/api/webhooks/{$provider}/{$property->slug}");

        return str_starts_with($url, 'https://') ? $url : null;
    }

    /**
     * @return array<string, array<string, ?string>>
     */
    public function credentials(Property $property): array
    {
        try {
            return $property->payment_credentials ?? [];
        } catch (DecryptException $e) {
            // APP_KEY trocada: as chaves salvas não abrem mais e o dono precisa salvar de novo.
            report($e);

            return [];
        }
    }

    /**
     * @param  array<string, ?string>  $credentials
     */
    public static function complete(string $provider, array $credentials): bool
    {
        return match ($provider) {
            'mercadopago' => filled($credentials['public_key'] ?? null) && filled($credentials['access_token'] ?? null),
            'stripe' => filled($credentials['publishable_key'] ?? null) && filled($credentials['secret_key'] ?? null),
            default => false,
        };
    }

    // ── Checkout ──

    /**
     * Situação do checkout como a página de pagamento mostra. Com $refresh, pergunta antes ao
     * gateway como está o pagamento em aberto (no máximo a cada poucos segundos).
     *
     * @return array<string, mixed>
     */
    public function checkout(Booking $booking, bool $refresh = false): array
    {
        $booking->loadMissing(['property', 'accommodations.photos']);
        $property = $booking->property;
        $open = $this->openPayment($booking);

        if ($refresh && $open && Cache::add("payment:refresh:{$open->id}", true, 4)) {
            $this->refresh($open);
            $booking->refresh();
        }
        if ($booking->status === BookingStatus::AwaitingPayment && ! $booking->payment_due_at?->isFuture()) {
            $this->expire($booking);
            $booking->refresh();
        }

        $settings = $this->settings($property);
        $methods = $booking->status === BookingStatus::AwaitingPayment ? $this->methodsFor($property, $booking->accommodations) : [];
        $credentials = $this->credentials($property);
        $subtotals = $booking->accommodations->mapWithKeys(fn ($a) => [$a->id => $a->pivot->subtotal])->all();
        $pixDiscount = array_sum($this->pixDiscounts($booking->accommodations, $subtotals));
        $promo = $booking->accommodations->map(fn (Accommodation $a) => $a->pixPromo())->filter()->sortBy(fn ($p) => $p['ends_at']?->getTimestamp() ?? PHP_INT_MAX)->first();
        $last = $booking->payments()->latest('id')->first();

        return [
            'code' => $booking->code,
            'status' => $booking->status,
            'due_at' => $booking->payment_due_at?->toAtomString(),
            'paid_at' => $booking->paid_at?->toAtomString(),
            'payment_method' => $booking->payment_method,
            'property' => [
                'name' => $property->name,
                'slug' => $property->slug,
                'whatsapp' => $property->whatsapp,
            ],
            'booking' => [
                'check_in' => $booking->check_in->toDateString(),
                'check_out' => $booking->check_out->toDateString(),
                'nights' => $booking->nights(),
                'arrival_time' => Booking::hm($booking->arrival_time),
                'start_time' => Booking::hm($booking->start_time),
                'end_time' => Booking::hm($booking->end_time),
                'hours' => $booking->hours(),
                'guests' => $booking->guests,
                'guest_name' => $booking->guest_name,
                'guest_email' => $booking->guest_email,
                'total' => $booking->total,
                'pix_discount' => $booking->pix_discount,
                'accommodations' => $booking->accommodations->map(fn (Accommodation $a) => [
                    'name' => $a->name,
                    'subtotal' => $a->pivot->subtotal,
                    'photo' => ($photo = $a->photos->first()) ? PhotoUrl::from($photo->path) : null,
                ])->values(),
            ],
            'methods' => [
                'pix' => in_array(Payment::PIX, $methods, true) ? [
                    'provider' => $settings['pix'],
                    'discount' => $pixDiscount,
                    'total' => $booking->total - $pixDiscount,
                    'percent' => $promo['percent'] ?? 0,
                    'promo_ends_at' => $pixDiscount ? ($promo['ends_at'] ?? null)?->toAtomString() : null,
                ] : null,
                'card' => in_array(Payment::CARD, $methods, true) ? [
                    'provider' => $settings['card'],
                    'total' => $booking->total,
                    // Parcelas só no Mercado Pago (na Stripe o parcelamento brasileiro é outro produto).
                    'installments' => $settings['card'] === 'stripe' ? 1 : $settings['installments'],
                    'public_key' => match ($settings['card']) {
                        'mercadopago' => $credentials['mercadopago']['public_key'] ?? null,
                        'stripe' => $credentials['stripe']['publishable_key'] ?? null,
                        default => null,
                    },
                ] : null,
            ],
            'payment' => $last ? $this->present($last) : null,
        ];
    }

    /**
     * Gera o Pix (ou devolve o que já está valendo).
     *
     * @param  array{name: string, email: string}  $payer
     */
    public function startPix(Booking $booking, array $payer): Payment
    {
        $this->assertPayable($booking, Payment::PIX);

        $open = $this->openPayment($booking);
        if ($open?->method === Payment::PIX && $open->expires_at?->isAfter(now()->addMinutes(2))) {
            return $open;
        }

        $booking->loadMissing('accommodations');
        $subtotals = $booking->accommodations->mapWithKeys(fn ($a) => [$a->id => $a->pivot->subtotal])->all();
        $discounts = $this->pixDiscounts($booking->accommodations, $subtotals);
        $this->extendDue($booking);

        return $this->charge($booking, Payment::PIX, [
            'amount' => $booking->total - array_sum($discounts),
            'discount' => array_sum($discounts),
            'data' => ['discounts' => $discounts],
        ], fn (Gateway $gateway, Payment $payment) => $gateway->pix($payment, $booking, $payer));
    }

    /**
     * Cobra o cartão (Mercado Pago e simulado) ou abre a cobrança que o navegador confirma (Stripe).
     *
     * @param  array<string, mixed>  $input
     */
    public function startCard(Booking $booking, array $input): Payment
    {
        $this->assertPayable($booking, Payment::CARD);
        $settings = $this->settings($booking->property);

        if ((int) ($input['installments'] ?? 1) > $settings['installments']) {
            throw ValidationException::withMessages(['installments' => "Em até {$settings['installments']}x."]);
        }

        return $this->charge($booking, Payment::CARD, ['amount' => $booking->total],
            fn (Gateway $gateway, Payment $payment) => $gateway->card($payment, $booking, $input));
    }

    /** Demonstração: "pagar" o Pix simulado em aberto. */
    public function simulatePix(Booking $booking): void
    {
        $open = $this->openPayment($booking);

        if (! $open || $open->provider !== self::SIMULATED || $open->method !== Payment::PIX) {
            throw ValidationException::withMessages(['payment' => 'Gere o Pix antes de simular o pagamento.']);
        }

        $this->markPaid($open);
    }

    /**
     * Aviso de um gateway. Devolve false quando o aviso não é válido (assinatura errada).
     */
    public function webhook(Property $property, string $provider, Request $request): bool
    {
        if (! in_array($provider, $this->configured($property), true)) {
            return true; // gateway desligado: nada a fazer, e o gateway para de insistir
        }

        $id = $this->gateway($property, $provider)->webhookPaymentId($request);
        if ($id === null) {
            return false;
        }

        $payment = Payment::where('provider', $provider)->where('external_id', $id)
            ->whereHas('booking', fn ($query) => $query->where('property_id', $property->id))
            ->first();

        if ($payment) {
            $this->refresh($payment, force: true);
        }

        return true;
    }

    /**
     * Pergunta ao gateway como está o pagamento e aplica a resposta.
     */
    public function refresh(Payment $payment, bool $force = false): void
    {
        if ($payment->provider === self::SIMULATED || ! $payment->external_id) {
            return;
        }
        // Pago ou estornado só muda por aviso do gateway (um estorno, por exemplo).
        if (! $force && $payment->status !== Payment::PENDING) {
            return;
        }

        try {
            $result = $this->gateway($payment->booking->property, $payment->provider)->fetch($payment);
        } catch (GatewayException $e) {
            report($e);

            return;
        }

        if ($result['reference'] && $result['reference'] !== $payment->reference()) {
            Log::error('Pagamento com referência diferente da nossa', ['payment' => $payment->id, 'reference' => $result['reference']]);

            return;
        }

        if ($result['status'] === Payment::PAID) {
            if ($result['amount'] !== null && $result['amount'] !== $payment->amount) {
                Log::error('Pagamento aprovado com valor diferente do cobrado', ['payment' => $payment->id, 'amount' => $result['amount']]);

                return;
            }
            $this->markPaid($payment);

            return;
        }

        $payment->update(['status' => $result['status'], 'data' => [...($payment->data ?? []), ...$result['data']]]);
    }

    /**
     * Pedidos que passaram do prazo sem pagar viram Expired (antes, uma última consulta ao
     * gateway, para não expirar quem acabou de pagar e o aviso ainda não chegou).
     */
    public function expireStale(Property $property): void
    {
        $property->bookings()
            ->where('status', BookingStatus::AwaitingPayment)
            ->where('payment_due_at', '<=', now())
            ->get()
            ->each(fn (Booking $booking) => $this->expire($booking));
    }

    public function expire(Booking $booking): void
    {
        if ($open = $this->openPayment($booking, includeOverdue: true)) {
            $this->refresh($open);
        }

        DB::transaction(function () use ($booking) {
            $updated = Booking::whereKey($booking->id)->where('status', BookingStatus::AwaitingPayment)
                ->where('payment_due_at', '<=', now())
                ->update(['status' => BookingStatus::Expired->value, 'updated_at' => now()]);

            if ($updated) {
                Payment::where('booking_id', $booking->id)->where('status', Payment::PENDING)->update(['status' => Payment::EXPIRED]);
            }
        });
    }

    /**
     * Pagamento aprovado: confirma a reserva (com o desconto do Pix no total) e avisa o dono.
     *
     * Se ele chega depois de a reserva expirar, confirma do mesmo jeito quando as datas ainda
     * estão livres. Se não estão (ou o dono cancelou, ou já tinha sido paga), o pagamento fica
     * marcado como "a estornar" para o dono devolver pelo gateway.
     */
    public function markPaid(Payment $payment): void
    {
        $confirmed = DB::transaction(function () use ($payment) {
            $booking = Booking::whereKey($payment->booking_id)->lockForUpdate()->firstOrFail();
            $payment->refresh();

            if ($payment->status === Payment::PAID) {
                return null;
            }

            $closed = ! in_array($booking->status, [BookingStatus::AwaitingPayment, BookingStatus::Expired], true)
                || ($booking->status === BookingStatus::Expired && ! $this->stillFree($booking));
            $payment->update([
                'status' => Payment::PAID,
                'paid_at' => now(),
                'data' => [...($payment->data ?? []), ...($closed ? ['refund_needed' => true] : [])],
            ]);

            if ($closed) {
                Log::warning('Pagamento aprovado para uma reserva que não pode mais ser confirmada', ['payment' => $payment->id, 'booking' => $booking->code]);

                return null;
            }

            $discounts = $payment->data['discounts'] ?? [];
            foreach ($booking->accommodations as $accommodation) {
                if ($discount = (int) ($discounts[$accommodation->id] ?? 0)) {
                    $booking->accommodations()->updateExistingPivot($accommodation->id, ['subtotal' => max(0, $accommodation->pivot->subtotal - $discount)]);
                }
            }

            $booking->update([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
                'paid_at' => now(),
                'payment_method' => $payment->method,
                'pix_discount' => $payment->discount,
                'total' => $payment->amount,
            ]);
            Payment::where('booking_id', $booking->id)->whereKeyNot($payment->id)->where('status', Payment::PENDING)->update(['status' => Payment::EXPIRED]);

            return $booking;
        });

        if ($confirmed) {
            try {
                Notification::send($confirmed->property->users, new NewBookingRequest($confirmed->load('accommodations')));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * O que o checkout mostra de um pagamento (sem as contas internas).
     *
     * @return array<string, mixed>
     */
    public function present(Payment $payment): array
    {
        $data = $payment->data ?? [];
        $open = $payment->isPending();

        return [
            'method' => $payment->method,
            'provider' => $payment->provider,
            'status' => $payment->status === Payment::PENDING && ! $open ? Payment::EXPIRED : $payment->status,
            'amount' => $payment->amount,
            'discount' => $payment->discount,
            'expires_at' => $payment->expires_at?->toAtomString(),
            'qr_code' => $open ? ($data['qr_code'] ?? null) : null,
            'ticket_url' => $open ? ($data['ticket_url'] ?? null) : null,
            'client_secret' => $open ? ($data['client_secret'] ?? null) : null,
            'brand' => $data['brand'] ?? null,
            'last4' => $data['last4'] ?? null,
            'installments' => $data['installments'] ?? null,
            'message' => $data['message'] ?? null,
            'simulated' => $payment->provider === self::SIMULATED,
        ];
    }

    // ── Internos ──

    /**
     * Cria a tentativa, chama o gateway e guarda a resposta.
     *
     * @param  array<string, mixed>  $attributes
     * @param  callable(Gateway, Payment): array<string, mixed>  $call
     */
    private function charge(Booking $booking, string $method, array $attributes, callable $call): Payment
    {
        $provider = $this->settings($booking->property)[$method];
        $payment = $booking->payments()->create([
            'provider' => $provider,
            'method' => $method,
            'expires_at' => $booking->payment_due_at,
            ...$attributes,
        ]);

        try {
            $result = $call($this->gateway($booking->property, $provider), $payment);
        } catch (GatewayException $e) {
            $payment->update(['status' => Payment::FAILED, 'data' => [...($payment->data ?? []), 'message' => $e->getMessage()]]);

            throw ValidationException::withMessages(['payment' => $e->getMessage()]);
        }

        $payment->update([
            'external_id' => $result['external_id'],
            'status' => $result['status'] === Payment::PAID ? Payment::PENDING : $result['status'],
            'data' => [...($payment->data ?? []), ...$result['data']],
            'expires_at' => $result['expires_at'] ?? $payment->expires_at,
        ]);

        if ($result['status'] === Payment::PAID) {
            $this->markPaid($payment);
        }

        return $payment->fresh();
    }

    private function assertPayable(Booking $booking, string $method): void
    {
        if (! $booking->awaitsPayment()) {
            throw ValidationException::withMessages(['payment' => $booking->status === BookingStatus::Confirmed
                ? 'Esta reserva já está paga e confirmada.'
                : 'O prazo para pagar esta reserva acabou. Faça um novo pedido no site.']);
        }

        if (! in_array($method, $this->methodsFor($booking->property, $booking->accommodations), true)) {
            throw ValidationException::withMessages(['payment' => 'Essa forma de pagamento não está disponível para esta reserva.']);
        }
    }

    /**
     * Um Pix gerado perto do fim do prazo ganha alguns minutos, sem passar do máximo.
     */
    private function extendDue(Booking $booking): void
    {
        $wanted = now()->addMinutes(self::PIX_MIN_MINUTES);
        $limit = $booking->created_at->copy()->addMinutes(self::MAX_HOLD_MINUTES);

        if ($booking->payment_due_at->lt($wanted)) {
            $booking->update(['payment_due_at' => $wanted->min($limit)]);
        }
    }

    private function openPayment(Booking $booking, bool $includeOverdue = false): ?Payment
    {
        return $booking->payments()->where('status', Payment::PENDING)
            ->when(! $includeOverdue, fn ($query) => $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())))
            ->latest('id')
            ->first();
    }

    /** As datas de uma reserva expirada ainda estão livres (ninguém pegou depois)? */
    private function stillFree(Booking $booking): bool
    {
        return $booking->accommodations->every(fn (Accommodation $accommodation) => $booking->isHourly()
            ? $this->availability->isHourAvailable($accommodation, $booking->check_in, Booking::hm($booking->start_time), Booking::hm($booking->end_time), $booking)
            : $this->availability->isAvailable($accommodation, $booking->check_in, $booking->check_out, $booking));
    }

    private function isDemo(Property $property): bool
    {
        return app(DemoService::class)->isDemo($property);
    }
}
