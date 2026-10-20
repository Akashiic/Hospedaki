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
 * "Seu site > Configurações > Pagamentos": o Mercado Pago e a Stripe (cada um com as chaves de
 * teste e as de produção, o modo em uso e ligado ou desligado), qual cobra o Pix e qual cobra
 * o cartão e o máximo de parcelas.
 *
 * As chaves secretas ficam criptografadas no banco e nunca voltam inteiras: a resposta mostra
 * só os 4 últimos caracteres. Para trocar, o dono cola a chave nova; em branco, fica a salva.
 * O painel envia sempre public_key e secret_key; aqui viram os nomes de cada gateway.
 */
class PaymentController extends Controller
{
    /** Nomes das chaves em cada gateway. */
    private const FIELDS = [
        'mercadopago' => ['public' => 'public_key', 'secret' => 'access_token'],
        'stripe' => ['public' => 'publishable_key', 'secret' => 'secret_key'],
    ];

    /** Como começa cada chave, por modo. No Mercado Pago, usuários de teste também usam APP_USR-. */
    private const PATTERNS = [
        'mercadopago' => [
            'test' => ['public' => '/^(TEST|APP_USR)-[A-Za-z0-9-]+$/', 'secret' => '/^(TEST|APP_USR)-[A-Za-z0-9-]+$/'],
            'live' => ['public' => '/^APP_USR-[A-Za-z0-9-]+$/', 'secret' => '/^APP_USR-[A-Za-z0-9-]+$/'],
        ],
        'stripe' => [
            'test' => ['public' => '/^pk_test_[A-Za-z0-9]+$/', 'secret' => '/^(sk|rk)_test_[A-Za-z0-9]+$/'],
            'live' => ['public' => '/^pk_live_[A-Za-z0-9]+$/', 'secret' => '/^(sk|rk)_live_[A-Za-z0-9]+$/'],
        ],
    ];

    private const HINTS = [
        'mercadopago' => [
            'test' => ['public' => 'A Public Key de teste começa com TEST- (ou APP_USR-, de um usuário de teste).', 'secret' => 'O Access Token de teste começa com TEST- (ou APP_USR-, de um usuário de teste).'],
            'live' => ['public' => 'A Public Key de produção começa com APP_USR-.', 'secret' => 'O Access Token de produção começa com APP_USR-. O que começa com TEST- é de teste.'],
        ],
        'stripe' => [
            'test' => ['public' => 'A chave publicável de teste começa com pk_test_.', 'secret' => 'A chave secreta de teste começa com sk_test_.'],
            'live' => ['public' => 'A chave publicável de produção começa com pk_live_.', 'secret' => 'A chave secreta de produção começa com sk_live_.'],
        ],
    ];

    public function __construct(private readonly PaymentService $payments) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user()->property);
    }

    /**
     * Salva (e testa) as chaves de um modo. Se o gateway ainda não tinha chaves funcionando,
     * ele passa a usar estas na hora; senão continua no modo em que estava.
     */
    public function updateGateway(Request $request, string $provider): JsonResponse
    {
        abort_unless(isset(self::FIELDS[$provider]), 404);
        $property = $request->user()->property;
        $this->blockDemo($property);

        $mode = $request->validate(['mode' => ['required', Rule::in(PaymentService::MODES)]])['mode'];
        ['public' => $public, 'secret' => $secret] = self::FIELDS[$provider];
        $gateways = $this->payments->gateways($property);
        $gateway = $gateways[$provider];
        $saved = $gateway[$mode];
        $hints = self::HINTS[$provider][$mode];

        $data = $request->validate([
            'public_key' => ['required', 'string', 'max:200', 'regex:'.self::PATTERNS[$provider][$mode]['public']],
            'secret_key' => [filled($saved[$secret] ?? null) ? 'nullable' : 'required', 'string', 'max:300', 'regex:'.self::PATTERNS[$provider][$mode]['secret']],
            'webhook_secret' => ['nullable', 'string', 'max:200'],
            'clear_webhook_secret' => ['sometimes', 'boolean'],
        ], [
            'public_key.regex' => $hints['public'],
            'secret_key.regex' => $hints['secret'],
            'secret_key.required' => $provider === 'mercadopago' ? 'Cole o Access Token.' : 'Cole a chave secreta.',
        ]);

        $keys = [
            $public => trim($data['public_key']),
            $secret => filled($data['secret_key'] ?? null) ? trim($data['secret_key']) : $saved[$secret],
            'webhook_secret' => $request->boolean('clear_webhook_secret') ? null : (filled($data['webhook_secret'] ?? null) ? trim($data['webhook_secret']) : ($saved['webhook_secret'] ?? null)),
        ];

        if ($provider === 'mercadopago' && $mode === 'live' && PaymentService::keyMode($provider, $keys[$secret]) === 'test') {
            throw ValidationException::withMessages(['secret_key' => $hints['secret']]);
        }

        try {
            $this->payments->make($property, $provider, $keys)->verify();
        } catch (GatewayException $e) {
            throw ValidationException::withMessages(['secret_key' => $e->getMessage()]);
        }

        $wasActive = $this->payments->active($property, $provider) !== [];
        $gateway[$mode] = $keys;
        if (! $wasActive) {
            $gateway['mode'] = $mode;
            $gateway['enabled'] = true;
        }
        $this->save($property, [...$gateways, $provider => $gateway], assign: ! $wasActive ? $provider : null);

        return $this->respond($property);
    }

    /**
     * Liga ou desliga o gateway e escolhe o modo em uso (teste ou produção).
     */
    public function updateState(Request $request, string $provider): JsonResponse
    {
        abort_unless(isset(self::FIELDS[$provider]), 404);
        $property = $request->user()->property;
        $this->blockDemo($property);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::in(PaymentService::MODES)],
        ]);

        $gateways = $this->payments->gateways($property);
        if (! PaymentService::complete($provider, $gateways[$provider][$data['mode']])) {
            throw ValidationException::withMessages(['mode' => $data['mode'] === 'live'
                ? 'Salve as chaves de produção antes de usar o modo produção.'
                : 'Salve as chaves de teste antes de usar o modo de testes.']);
        }

        $gateways[$provider]['enabled'] = $data['enabled'];
        $gateways[$provider]['mode'] = $data['mode'];
        $this->save($property, $gateways, assign: $data['enabled'] ? $provider : null);

        return $this->respond($property);
    }

    /** Apaga as chaves de um modo. Sem as do modo em uso, o gateway para de cobrar. */
    public function destroyGateway(Request $request, string $provider): JsonResponse
    {
        abort_unless(isset(self::FIELDS[$provider]), 404);
        $property = $request->user()->property;
        $this->blockDemo($property);
        $mode = $request->validate(['mode' => ['required', Rule::in(PaymentService::MODES)]])['mode'];

        $gateways = $this->payments->gateways($property);
        $gateways[$provider][$mode] = [];
        if ($gateways[$provider]['mode'] === $mode) {
            $gateways[$provider]['enabled'] = false;
        }
        $this->save($property, $gateways);

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
            'pix.in' => 'Ative esse gateway antes de usar no Pix.',
            'card.in' => 'Ative esse gateway antes de usar no cartão.',
        ]);

        $property->update(['settings' => ['payments' => [
            'pix' => $data['pix'] ?? null,
            'card' => $data['card'] ?? null,
            'installments' => $data['installments'],
        ]] + ($property->settings ?? [])]);

        return $this->respond($property);
    }

    /**
     * Grava os gateways. Um gateway que acaba de ficar ativo ($assign) passa a cobrar o Pix e o
     * cartão que não têm quem cobre: nunca escolhidos, já dele ou de um gateway que está
     * desligado. Um "Desligado" escolhido pelo dono continua desligado.
     *
     * @param  array<string, mixed>  $gateways
     */
    private function save(Property $property, array $gateways, ?string $assign = null): void
    {
        $property->payment_credentials = $gateways;
        $saved = ($property->settings ?? [])['payments'] ?? [];

        if ($assign) {
            $configured = $this->payments->configured($property);
            $pick = fn (string $method) => ! array_key_exists($method, $saved)
                || ($saved[$method] !== null && ! in_array($saved[$method], $configured, true))
                || $saved[$method] === $assign
                ? $assign : $saved[$method];
            $property->settings = ['payments' => [
                'pix' => $pick('pix'),
                'card' => $pick('card'),
                'installments' => $this->payments->settings($property)['installments'],
            ]] + ($property->settings ?? []);
        }
        $property->save();
    }

    /** Na demonstração o painel é público: chaves de verdade aqui receberiam o dinheiro de outros visitantes. */
    private function blockDemo(Property $property): void
    {
        if (app(DemoService::class)->isDemo($property)) {
            throw ValidationException::withMessages(['gateway' => 'Na demonstração os pagamentos são simulados e as chaves ficam travadas. No site da sua pousada você conecta sua conta.']);
        }
    }

    private function respond(Property $property): JsonResponse
    {
        $gateways = [];

        foreach ($this->payments->gateways($property) as $provider => $gateway) {
            ['public' => $public, 'secret' => $secret] = self::FIELDS[$provider];
            $keys = fn (string $mode) => [
                'configured' => PaymentService::complete($provider, $gateway[$mode]),
                'public_key' => $gateway[$mode][$public] ?? null,
                // Só o final, para o dono reconhecer qual chave está salva.
                'secret_hint' => filled($gateway[$mode][$secret] ?? null) ? '•••• '.substr($gateway[$mode][$secret], -4) : null,
                'webhook_secret' => filled($gateway[$mode]['webhook_secret'] ?? null),
            ];
            $gateways[$provider] = [
                'enabled' => $gateway['enabled'],
                'mode' => $gateway['mode'],
                // Ligado e com as chaves do modo em uso: é o que pode cobrar.
                'active' => $this->payments->active($property, $provider) !== [],
                'test' => $keys('test'),
                'live' => $keys('live'),
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
}
