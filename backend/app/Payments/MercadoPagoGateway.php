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
 * Mercado Pago pela API de pagamentos (/v1/payments): Pix com QR Code e cartão com o token
 * gerado no navegador pelo SDK (os campos do cartão são iframes do Mercado Pago).
 *
 * @see https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
 */
class MercadoPagoGateway implements Gateway
{
    private const API = 'https://api.mercadopago.com';

    /** Motivos de recusa do cartão, na língua do hóspede. */
    private const REJECTIONS = [
        'cc_rejected_insufficient_amount' => 'O cartão não tem limite suficiente.',
        'cc_rejected_bad_filled_security_code' => 'O código de segurança está errado.',
        'cc_rejected_bad_filled_date' => 'A validade do cartão está errada.',
        'cc_rejected_bad_filled_card_number' => 'O número do cartão está errado.',
        'cc_rejected_bad_filled_other' => 'Confira os dados do cartão.',
        'cc_rejected_call_for_authorize' => 'O banco pediu para você autorizar a compra. Ligue para ele e tente de novo.',
        'cc_rejected_card_disabled' => 'O cartão está desativado. Ligue para o banco ou use outro cartão.',
        'cc_rejected_duplicated_payment' => 'Esse pagamento já foi feito. Confira seu extrato antes de tentar de novo.',
        'cc_rejected_high_risk' => 'O pagamento foi recusado pela análise de segurança. Tente outro cartão ou o Pix.',
        'cc_rejected_max_attempts' => 'Muitas tentativas com esse cartão. Use outro cartão ou o Pix.',
    ];

    /**
     * @param  array{public_key?: string, access_token?: string, webhook_secret?: ?string}  $credentials
     */
    public function __construct(private readonly array $credentials, private readonly ?string $notificationUrl = null) {}

    public function pix(Payment $payment, Booking $booking, array $payer): array
    {
        $body = $this->ok($this->http($payment->reference())->post('/v1/payments', array_filter([
            'transaction_amount' => round($payment->amount / 100, 2),
            'description' => self::description($booking),
            'payment_method_id' => 'pix',
            'external_reference' => $payment->reference(),
            'notification_url' => $this->notificationUrl,
            // Formato exigido: 2026-10-20T15:30:00.000-03:00.
            'date_of_expiration' => $payment->expires_at?->format('Y-m-d\TH:i:s.vP'),
            'payer' => ['email' => $payer['email'], 'first_name' => strtok($payer['name'], ' ')],
        ])));

        $transaction = $body['point_of_interaction']['transaction_data'] ?? [];

        return [
            'external_id' => (string) $body['id'],
            'status' => self::status($body['status'] ?? null),
            'data' => [
                'qr_code' => $transaction['qr_code'] ?? null,
                'ticket_url' => $transaction['ticket_url'] ?? null,
            ],
            'expires_at' => isset($body['date_of_expiration']) ? CarbonImmutable::parse($body['date_of_expiration']) : null,
        ];
    }

    public function card(Payment $payment, Booking $booking, array $input): array
    {
        $body = $this->ok($this->http($payment->reference())->post('/v1/payments', array_filter([
            'transaction_amount' => round($payment->amount / 100, 2),
            'token' => $input['token'],
            'description' => self::description($booking),
            'installments' => (int) ($input['installments'] ?? 1),
            'payment_method_id' => $input['payment_method_id'],
            'issuer_id' => isset($input['issuer_id']) && $input['issuer_id'] !== '' ? (int) $input['issuer_id'] : null,
            'external_reference' => $payment->reference(),
            'notification_url' => $this->notificationUrl,
            'payer' => array_filter([
                'email' => $input['email'],
                'identification' => isset($input['identification_number']) ? [
                    'type' => $input['identification_type'] ?? 'CPF',
                    'number' => preg_replace('/\D/', '', (string) $input['identification_number']),
                ] : null,
            ]),
        ], fn ($value) => $value !== null)));

        return [
            'external_id' => (string) $body['id'],
            'status' => self::status($body['status'] ?? null),
            'data' => $this->cardData($body),
        ];
    }

    public function fetch(Payment $payment): array
    {
        $body = $this->ok($this->http()->get('/v1/payments/'.urlencode((string) $payment->external_id)));

        return [
            'status' => self::status($body['status'] ?? null),
            'amount' => isset($body['transaction_amount']) ? (int) round($body['transaction_amount'] * 100) : null,
            'reference' => $body['external_reference'] ?? null,
            'data' => $payment->method === Payment::CARD ? $this->cardData($body) : [],
        ];
    }

    /**
     * O aviso chega como {"type": "payment", "data": {"id": "123"}} (ou na query, nos avisos
     * antigos). Com a assinatura secreta salva, confere o x-signature: HMAC-SHA256 de
     * "id:{id};request-id:{x-request-id};ts:{ts};". Sem ela, vale a consulta que vem depois.
     */
    public function webhookPaymentId(Request $request): ?string
    {
        $type = $request->input('type') ?? $request->query('type') ?? $request->query('topic');
        // O PHP troca o ponto de "data.id" na query por "_".
        $id = (string) ($request->input('data.id') ?? $request->query('data_id') ?? $request->query('id') ?? '');

        if ($type !== 'payment' || $id === '' || ! preg_match('/^[A-Za-z0-9]+$/', $id)) {
            return null;
        }

        if ($secret = $this->credentials['webhook_secret'] ?? null) {
            parse_str(str_replace(',', '&', (string) $request->header('x-signature')), $parts);
            $manifest = 'id:'.strtolower($id).';request-id:'.$request->header('x-request-id').';ts:'.($parts['ts'] ?? '').';';

            if (! isset($parts['ts'], $parts['v1']) || ! hash_equals(hash_hmac('sha256', $manifest, $secret), (string) $parts['v1'])) {
                return null;
            }
        }

        return $id;
    }

    public function verify(): void
    {
        $response = $this->http()->get('/users/me');

        if ($response->status() === 401 || $response->status() === 403) {
            throw new GatewayException('O Mercado Pago não aceitou esse Access Token. Copie de novo em Suas integrações > Credenciais.');
        }
        $this->ok($response);
    }

    /** Descrição que aparece no extrato e no comprovante. */
    public static function description(Booking $booking): string
    {
        return mb_substr('Reserva '.$booking->code.' - '.$booking->property->name, 0, 120);
    }

    public static function status(?string $status): string
    {
        return match ($status) {
            'approved' => Payment::PAID,
            'rejected' => Payment::FAILED,
            'cancelled' => Payment::EXPIRED,
            'refunded', 'charged_back' => Payment::REFUNDED,
            default => Payment::PENDING, // pending, in_process, authorized, in_mediation
        };
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function cardData(array $body): array
    {
        return array_filter([
            'brand' => $body['payment_method_id'] ?? null,
            'last4' => $body['card']['last_four_digits'] ?? null,
            'installments' => $body['installments'] ?? null,
            'message' => ($body['status'] ?? null) === 'rejected'
                ? (self::REJECTIONS[$body['status_detail'] ?? ''] ?? 'O cartão foi recusado. Confira os dados ou tente outro cartão.')
                : (($body['status'] ?? null) === 'in_process' ? 'O pagamento está em análise. Avisamos aqui assim que for aprovado.' : null),
        ], fn ($value) => $value !== null);
    }

    private function http(?string $idempotencyKey = null): PendingRequest
    {
        return Http::baseUrl(self::API)
            ->withToken((string) ($this->credentials['access_token'] ?? ''))
            ->acceptJson()
            ->timeout(20)
            // A mesma tentativa enviada duas vezes (rede caiu no meio) não cobra duas vezes.
            ->when($idempotencyKey, fn (PendingRequest $http) => $http->withHeaders(['X-Idempotency-Key' => $idempotencyKey]));
    }

    /**
     * @return array<string, mixed>
     */
    private function ok(Response $response): array
    {
        if ($response->successful() && is_array($response->json())) {
            return $response->json();
        }

        Log::warning('Mercado Pago respondeu com erro', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 2000)]);

        throw new GatewayException($response->status() === 401
            ? 'O Mercado Pago recusou as chaves da pousada. Avise a pousada pelo WhatsApp.'
            : 'O Mercado Pago não respondeu como esperado. Tente de novo em instantes.');
    }
}
