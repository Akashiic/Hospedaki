<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Checkout do site: o hóspede chega pelo link com o token secreto da reserva (o código curto
 * da reserva não basta, porque a página mostra nome e valores).
 */
class CheckoutController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Situação do pagamento; com ?refresh=1 pergunta antes ao gateway (o checkout chama em intervalos). */
    public function show(Request $request, string $token): JsonResponse
    {
        return response()->json($this->payments->checkout($this->booking($token), $request->boolean('refresh')));
    }

    public function pix(Request $request, string $token): JsonResponse
    {
        $booking = $this->booking($token);
        $data = $request->validate([
            'email' => [$booking->guest_email ? 'nullable' : 'required', 'email:rfc', 'max:120'],
        ], ['email.required' => 'Informe seu e-mail: o comprovante do Pix vai para ele.']);

        $this->rememberEmail($booking, $data['email'] ?? null);
        $this->payments->startPix($booking, ['name' => $booking->guest_name, 'email' => $booking->guest_email]);

        return response()->json($this->payments->checkout($booking->fresh()));
    }

    public function card(Request $request, string $token): JsonResponse
    {
        $booking = $this->booking($token);
        $provider = $this->payments->settings($booking->property)['card'];

        // Mercado Pago: token do cartão gerado pelo SDK no navegador. Stripe: nada do cartão
        // (o navegador confirma com o client_secret). Simulado: só o final, para a demonstração.
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:120'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'token' => [$provider === 'mercadopago' ? 'required' : 'prohibited', 'string', 'max:200'],
            'payment_method_id' => [$provider === 'mercadopago' ? 'required' : 'nullable', 'string', 'max:40'],
            'issuer_id' => ['nullable', 'string', 'max:40'],
            'identification_type' => ['nullable', 'string', 'max:10'],
            'identification_number' => ['nullable', 'string', 'max:20'],
            'last4' => ['nullable', 'digits:4'],
            'brand' => ['nullable', 'string', 'max:20'],
        ], [
            'email.required' => 'Informe seu e-mail: o comprovante vai para ele.',
            'token.required' => 'Não foi possível ler o cartão. Confira os dados e tente de novo.',
        ]);

        $this->rememberEmail($booking, $data['email']);
        $this->payments->startCard($booking, $data);

        return response()->json($this->payments->checkout($booking->fresh()));
    }

    /** Demonstração: o botão "simular pagamento" do Pix. */
    public function simulate(string $token): JsonResponse
    {
        $booking = $this->booking($token);
        $this->payments->simulatePix($booking);

        return response()->json($this->payments->checkout($booking->fresh()));
    }

    private function booking(string $token): Booking
    {
        abort_unless(strlen($token) === 40, 404);

        return Booking::where('checkout_token', $token)->whereHas('property', fn ($query) => $query->where('active', true))
            ->with(['property', 'accommodations'])->firstOrFail();
    }

    private function rememberEmail(Booking $booking, ?string $email): void
    {
        if ($email && $email !== $booking->guest_email) {
            $booking->update(['guest_email' => $email]);
        }
    }
}
