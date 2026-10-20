<?php

namespace App\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe por PaymentIntents: o Pix é confirmado aqui e devolve o QR Code; o cartão é
 * confirmado no navegador pelo Stripe.js com o client_secret (o número nunca passa por nós).
 *
 * @see https://docs.stripe.com/payments/pix
 */
class StripeGateway implements Gateway
{
    private const API = 'https://api.stripe.com';

    /** Diferença máxima, em segundos, entre o horário assinado do webhook e o nosso. */
    private const WEBHOOK_TOLERANCE = 300;

    /**
     * @param  array{publishable_key?: string, secret_key?: string, webhook_secret?: ?string}  $credentials
     */
    public function __construct(private readonly array $credentials) {}

    public function pix(Payment $payment, Booking $booking, array $payer): array
    {
        $body = $this->ok($this->http($payment->reference())->post('/v1/payment_intents', array_filter([
            'amount' => $payment->amount,
            'currency' => 'brl',
            'payment_method_types' => ['pix'],
            'payment_method_data' => ['type' => 'pix', 'billing_details' => ['name' => $payer['name'], 'email' => $payer['email']]],
            'payment_method_options' => $payment->expires_at ? ['pix' => ['expires_at' => $payment->expires_at->getTimestamp()]] : null,
            'confirm' => 'true',
            'description' => MercadoPagoGateway::description($booking),
            'metadata' => ['payment' => $payment->reference(), 'booking' => $booking->code],
        ])));

        $qr = $body['next_action']['pix_display_qr_code'] ?? [];

        return [
            'external_id' => (string) $body['id'],
            'status' => self::status($body),
            'data' => [
                'qr_code' => $qr['data'] ?? null,
                'ticket_url' => $qr['hosted_instructions_url'] ?? null,
            ],
            'expires_at' => isset($qr['expires_at']) ? CarbonImmutable::createFromTimestamp($qr['expires_at']) : null,
        ];
    }

    public function card(Payment $payment, Booking $booking, array $input): array
    {
        $body = $this->ok($this->http($payment->reference())->post('/v1/payment_intents', [
            'amount' => $payment->amount,
            'currency' => 'brl',
            'payment_method_types' => ['card'],
            'description' => MercadoPagoGateway::description($booking),
            'receipt_email' => $input['email'],
            'metadata' => ['payment' => $payment->reference(), 'booking' => $booking->code],
        ]));

        return [
            'external_id' => (string) $body['id'],
            'status' => self::status($body),
            // O navegador confirma o cartão com isto; só serve para este pagamento.
            'data' => ['client_secret' => $body['client_secret'] ?? null],
        ];
    }

    public function fetch(Payment $payment): array
    {
        $body = $this->ok($this->http()->get('/v1/payment_intents/'.urlencode((string) $payment->external_id), [
            'expand' => ['latest_charge'],
        ]));
        $card = $body['latest_charge']['payment_method_details']['card'] ?? [];

        return [
            'status' => self::status($body),
            'amount' => isset($body['amount']) ? (int) $body['amount'] : null,
            'reference' => $body['metadata']['payment'] ?? null,
            'data' => array_filter([
                'brand' => $card['brand'] ?? null,
                'last4' => $card['last4'] ?? null,
                'message' => isset($body['last_payment_error']) ? 'O cartão foi recusado. Confira os dados ou tente outro cartão.' : null,
            ]),
        ];
    }

    /**
     * Confere o Stripe-Signature (t=...,v1=...): HMAC-SHA256 de "{t}.{corpo}" com o segredo do
     * webhook. Sem o segredo salvo, vale a consulta que vem depois.
     */
    public function webhookPaymentId(Request $request): ?string
    {
        $payload = $request->getContent();

        if ($secret = $this->credentials['webhook_secret'] ?? null) {
            $parts = collect(explode(',', (string) $request->header('Stripe-Signature')))
                ->map(fn ($part) => explode('=', trim($part), 2))
                ->filter(fn ($pair) => count($pair) === 2);
            $timestamp = (int) ($parts->first(fn ($pair) => $pair[0] === 't')[1] ?? 0);
            $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
            $valid = $parts->contains(fn ($pair) => $pair[0] === 'v1' && hash_equals($expected, $pair[1]));

            if (! $valid || abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE) {
                return null;
            }
        }

        $event = json_decode($payload, true);
        $object = $event['data']['object'] ?? [];

        if (! str_starts_with((string) ($event['type'] ?? ''), 'payment_intent.') || ($object['object'] ?? null) !== 'payment_intent') {
            return null;
        }

        return isset($object['id']) ? (string) $object['id'] : null;
    }

    public function verify(): void
    {
        $response = $this->http()->get('/v1/balance');

        if ($response->status() === 401 || $response->status() === 403) {
            throw new GatewayException('A Stripe não aceitou essa chave secreta. Copie de novo em Desenvolvedores > Chaves de API.');
        }
        $this->ok($response);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    public static function status(array $intent): string
    {
        return match ($intent['status'] ?? null) {
            'succeeded' => Payment::PAID,
            'canceled' => Payment::EXPIRED,
            // Depois de uma recusa o PaymentIntent volta a pedir um meio de pagamento.
            'requires_payment_method' => isset($intent['last_payment_error']) ? Payment::FAILED : Payment::PENDING,
            default => Payment::PENDING, // requires_action (Pix esperando), processing, requires_confirmation
        };
    }

    private function http(?string $idempotencyKey = null): PendingRequest
    {
        return Http::baseUrl(self::API)
            ->withToken((string) ($this->credentials['secret_key'] ?? ''))
            ->asForm()
            ->acceptJson()
            ->timeout(20)
            ->when($idempotencyKey, fn (PendingRequest $http) => $http->withHeaders(['Idempotency-Key' => $idempotencyKey]));
    }

    /**
     * @return array<string, mixed>
     */
    private function ok(Response $response): array
    {
        if ($response->successful() && is_array($response->json())) {
            return $response->json();
        }

        Log::warning('Stripe respondeu com erro', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)]);

        throw new GatewayException($response->status() === 401
            ? 'A Stripe recusou as chaves da pousada. Avise a pousada pelo WhatsApp.'
            : 'A Stripe não respondeu como esperado. Tente de novo em instantes.');
    }
}
