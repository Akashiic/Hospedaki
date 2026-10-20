<?php

namespace App\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Um gateway de pagamento (Mercado Pago, Stripe ou o simulado da demonstração).
 *
 * Os métodos devolvem o resultado já no formato do Hospedaki:
 *   external_id  id do pagamento no gateway
 *   status       Payment::PENDING, PAID, FAILED, EXPIRED ou REFUNDED
 *   data         o que o checkout precisa mostrar (copia e cola do Pix, client_secret...)
 *   expires_at   quando o Pix vence, se o gateway informar
 */
interface Gateway
{
    /**
     * Gera a cobrança Pix.
     *
     * @param  array{name: string, email: string}  $payer
     * @return array{external_id: string, status: string, data: array<string, mixed>, expires_at?: ?\DateTimeInterface}
     */
    public function pix(Payment $payment, Booking $booking, array $payer): array;

    /**
     * Cobra o cartão. O número nunca passa pelo Hospedaki: chega um token do SDK do gateway
     * (Mercado Pago) ou a cobrança é confirmada no navegador com o client_secret (Stripe).
     *
     * @param  array<string, mixed>  $input
     * @return array{external_id: string, status: string, data: array<string, mixed>}
     */
    public function card(Payment $payment, Booking $booking, array $input): array;

    /**
     * Situação atual do pagamento consultada no gateway (nunca confiamos só no webhook).
     *
     * @return array{status: string, amount: ?int, reference: ?string, data: array<string, mixed>}
     */
    public function fetch(Payment $payment): array;

    /**
     * Id do pagamento a que o aviso (webhook) se refere, ou null se ele não for válido.
     */
    public function webhookPaymentId(Request $request): ?string;

    /**
     * Confere se as chaves funcionam (lança uma exceção com a mensagem para o dono se não).
     */
    public function verify(): void;
}
