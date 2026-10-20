<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos dos gateways quando um pagamento muda. A URL leva a pousada, para saber com que
 * chaves consultar o pagamento: o aviso em si só diz qual pagamento mudou.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, Property $property, PaymentService $payments): JsonResponse
    {
        abort_unless(in_array($provider, PaymentService::PROVIDERS, true), 404);

        // Assinatura inválida: 400, e o gateway mostra a falha no painel dele.
        return $payments->webhook($property, $provider, $request)
            ? response()->json(['ok' => true])
            : response()->json(['message' => 'Assinatura inválida.'], 400);
    }
}
