<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Payments\GatewayException;
use App\Services\DemoService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Seu site > Configurações > Pagamentos": as chaves do Mercado Pago e da Stripe, qual cobra o
 * Pix e qual cobra o cartão e o máximo de parcelas.
 *
 * As chaves secretas ficam criptografadas no banco e nunca voltam inteiras: a resposta mostra
 * só os 4 últimos caracteres. Para trocar, o dono cola a chave nova; em branco, fica a salva.
 */
class PaymentController extends Controller
{
    /** Campos de cada gateway; os secretos nunca voltam inteiros. */
    private const FIELDS = [
        'mercadopago' => ['public' => 'public_key', 'secret' => 'access_token'],
        'stripe' => ['public' => 'publishable_key', 'secret' => 'secret_key'],
    ];

    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user()->property);
    }

    public function updateGateway(Request $request, string $provider, DemoService $demo): JsonResponse
    {
        abort_unless(isset(self::FIELDS[$provider]), 404);
        $property = $request->user()->property;

        // Na demonstração o painel é público: chaves de verdade aqui receberiam o dinheiro de outros visitantes.
        if ($demo->isDemo($property)) {
            throw ValidationException::withMessages(['gateway' => 'Na demonstração os pagamentos são simulados e as chaves ficam desligadas. No site da sua pousada você conecta sua conta.']);
        }

        ['public' => $public, 'secret' => $secret] = self::FIELDS[$provider];
        $saved = $this->payments->credentials($property)[$provider] ?? [];

        $data = $request->validate([
            $public => ['required', 'string', 'max:200', $provider === 'mercadopago' ? 'regex:/^(APP_USR|TEST)-[A-Za-z0-9-]+$/' : 'regex:/^pk_(live|test)_[A-Za-z0-9]+$/'],
            $secret => [filled($saved[$secret] ?? null) ? 'nullable' : 'required', 'string', 'max:300', $provider === 'mercadopago' ? 'regex:/^(APP_USR|TEST)-[A-Za-z0-9-]+$/' : 'regex:/^(sk|rk)_(live|test)_[A-Za-z0-9]+$/'],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
            'clear_webhook_secret' => ['sometimes', 'boolean'],
        ], [
            "{$public}.regex" => $provider === 'mercadopago' ? 'A Public Key começa com APP_USR- (ou TEST- nas credenciais de teste).' : 'A chave publicável começa com pk_live_ ou pk_test_.',
            "{$secret}.regex" => $provider === 'mercadopago' ? 'O Access Token começa com APP_USR- (ou TEST- nas credenciais de teste).' : 'A chave secreta começa com sk_live_ ou sk_test_.',
            "{$secret}.required" => $provider === 'mercadopago' ? 'Cole o Access Token.' : 'Cole a chave secreta.',
        ]);

        $credentials = [
            $public => trim($data[$public]),
            $secret => filled($data[$secret] ?? null) ? trim($data[$secret]) : $saved[$secret],
            'webhook_secret' => $request->boolean('clear_webhook_secret') ? null : (filled($data['webhook_secret'] ?? null) ? trim($data['webhook_secret']) : ($saved['webhook_secret'] ?? null)),
        ];

        // Chaves de teste e de produção não se misturam.
        if ($this->isTest($provider, $credentials[$public]) !== $this->isTest($provider, $credentials[$secret])) {
            throw ValidationException::withMessages([$secret => 'Uma chave é de teste e a outra é de produção. Use as duas do mesmo tipo.']);
        }

        try {
            $this->payments->make($property, $provider, $credentials)->verify();
        } catch (GatewayException $e) {
            throw ValidationException::withMessages([$secret => $e->getMessage()]);
        }

        $property->payment_credentials = [...$this->payments->credentials($property), $provider => $credentials];

        // Um gateway recém-conectado já passa a cobrar o que ainda não tinha quem cobrasse.
        $settings = $this->payments->settings($property);
        $fresh = ! PaymentService::complete($provider, $saved);
        $property->settings = ['payments' => [
            'pix' => $settings['pix'] ?? ($fresh ? $provider : null),
            'card' => $settings['card'] ?? ($fresh ? $provider : null),
            'installments' => $settings['installments'],
        ]] + ($property->settings ?? []);
        $property->save();

        return $this->respond($property);
    }

    public function destroyGateway(Request $request, string $provider, DemoService $demo): JsonResponse
    {
        abort_unless(isset(self::FIELDS[$provider]), 404);
        $property = $request->user()->property;
        abort_if($demo->isDemo($property), 422, 'Na demonstração não há chaves salvas.');

        $all = $this->payments->credentials($property);
        unset($all[$provider]);
        $property->payment_credentials = $all ?: null;
        // Quem cobrava pelo gateway removido deixa de cobrar (settings() já ignora o que não está configurado).
        $settings = $this->payments->settings($property);
        $property->settings = ['payments' => $settings] + ($property->settings ?? []);
        $property->save();

        return $this->respond($property);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $property = $request->user()->property;
        $configured = $this->payments->configured($property);

        $data = $request->validate([
            'pix' => ['nullable', Rule::in($configured)],
            'card' => ['nullable', Rule::in($configured)],
            'installments' => ['required', 'integer', 'min:1', 'max:12'],
        ], [
            'pix.in' => 'Conecte esse gateway antes de usar no Pix.',
            'card.in' => 'Conecte esse gateway antes de usar no cartão.',
        ]);

        $property->update(['settings' => ['payments' => [
            'pix' => $data['pix'] ?? null,
            'card' => $data['card'] ?? null,
            'installments' => $data['installments'],
        ]] + ($property->settings ?? [])]);

        return $this->respond($property);
    }

    private function respond(Property $property): JsonResponse
    {
        $credentials = $this->payments->credentials($property);
        $gateways = [];

        foreach (self::FIELDS as $provider => ['public' => $public, 'secret' => $secret]) {
            $saved = $credentials[$provider] ?? [];
            $gateways[$provider] = [
                'configured' => PaymentService::complete($provider, $saved),
                $public => $saved[$public] ?? null,
                // Só o final, para o dono reconhecer qual chave está salva.
                $secret.'_hint' => filled($saved[$secret] ?? null) ? '•••• '.substr($saved[$secret], -4) : null,
                'webhook_secret' => filled($saved['webhook_secret'] ?? null),
                'test' => filled($saved[$secret] ?? null) && $this->isTest($provider, $saved[$secret]),
                'webhook_url' => url("/api/webhooks/{$provider}/{$property->slug}"),
            ];
        }

        return response()->json([
            'demo' => app(DemoService::class)->isDemo($property),
            'gateways' => $gateways,
            'configured' => $this->payments->configured($property),
            ...$this->payments->settings($property),
        ]);
    }

    private function isTest(string $provider, string $key): bool
    {
        return $provider === 'mercadopago' ? str_starts_with($key, 'TEST-') : str_contains($key, '_test_');
    }
}
