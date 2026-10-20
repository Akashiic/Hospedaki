<?php

namespace App\Payments;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pagamento de mentira, só na pousada de demonstração: mostra o checkout funcionando sem
 * gateway e sem cobrar nada. O QR Code do Pix é um texto que não paga nada em banco nenhum;
 * o cartão de teste terminado em 0002 é recusado e os outros são aprovados.
 */
class SimulatedGateway implements Gateway
{
    public const DECLINED_LAST4 = '0002';

    public function pix(Payment $payment, Booking $booking, array $payer): array
    {
        return [
            'external_id' => 'sim_'.Str::lower(Str::random(16)),
            'status' => Payment::PENDING,
            'data' => [
                'qr_code' => 'HOSPEDAKI-DEMONSTRACAO|reserva '.$booking->code.'|R$ '.number_format($payment->amount / 100, 2, ',', '.').'|nenhum valor é cobrado',
                'simulated' => true,
            ],
        ];
    }

    public function card(Payment $payment, Booking $booking, array $input): array
    {
        $last4 = substr(preg_replace('/\D/', '', (string) ($input['last4'] ?? '')), -4) ?: '4242';
        $declined = $last4 === self::DECLINED_LAST4;

        return [
            'external_id' => 'sim_'.Str::lower(Str::random(16)),
            'status' => $declined ? Payment::FAILED : Payment::PAID,
            'data' => array_filter([
                'brand' => $input['brand'] ?? 'visa',
                'last4' => $last4,
                'installments' => (int) ($input['installments'] ?? 1),
                'message' => $declined ? 'Cartão recusado (é o cartão de teste de recusa). Use outro número.' : null,
                'simulated' => true,
            ]),
        ];
    }

    public function fetch(Payment $payment): array
    {
        return ['status' => $payment->status, 'amount' => $payment->amount, 'reference' => $payment->reference(), 'data' => []];
    }

    public function webhookPaymentId(Request $request): ?string
    {
        return null;
    }

    public function verify(): void {}
}
