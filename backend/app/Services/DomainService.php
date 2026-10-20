<?php

namespace App\Services;

use App\Http\Middleware\AllowSiteDomains;
use App\Models\Property;
use App\Support\Dns;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Domínio próprio do site da pousada (painel → Seu site → Domínio).
 *
 * O dono registra o domínio onde quiser (Registro.br, GoDaddy, Hostinger...) e aponta o DNS para
 * a Vercel, onde o site roda. Com VERCEL_TOKEN e VERCEL_PROJECT_ID, o domínio é ligado no projeto
 * sozinho e a verificação vem da própria Vercel; sem eles, o painel mostra os registros e confere o
 * DNS por conta própria (a equipe liga o domínio no projeto à mão).
 *
 * Situação: pending (esperando o DNS), active (no ar) ou misconfigured (DNS apontando para outro lugar).
 */
class DomainService
{
    /** Destinos do site na Vercel. */
    public const A_RECORD = '76.76.21.21';

    public const CNAME_TARGET = 'cname.vercel-dns.com';

    /** Sufixos de dois níveis comuns (o domínio raiz de loja.pousada.com.br é pousada.com.br). */
    private const TWO_LEVEL_SUFFIXES = ['com.br', 'net.br', 'org.br', 'tur.br', 'art.br', 'eco.br', 'blog.br', 'srv.br', 'app.br', 'dev.br', 'co.uk', 'com.ar', 'com.pt', 'com.mx'];

    public function __construct(private readonly Dns $dns) {}

    /**
     * O que o painel mostra: o domínio, a situação, os registros de DNS e se a ligação é automática.
     *
     * @return array<string, mixed>
     */
    public function state(Property $property): array
    {
        $saved = ($property->settings ?? [])['domain'] ?? [];
        $domain = $property->domain;

        return [
            'domain' => $domain,
            'status' => $domain ? ($saved['status'] ?? 'pending') : null,
            'checked_at' => $domain ? ($saved['checked_at'] ?? null) : null,
            'records' => $domain ? $this->records($domain) : [],
            // Registros extras que a Vercel pede quando o domínio já foi usado em outra conta.
            'verification' => $domain ? ($saved['verification'] ?? []) : [],
            'automatic' => $this->vercelReady(),
            'default_url' => rtrim((string) config('app.frontend_url'), '/'),
        ];
    }

    /**
     * Liga um domínio novo (trocando o anterior, se houver) e já confere o DNS.
     */
    public function connect(Property $property, string $input): void
    {
        $domain = self::normalize($input);

        if (! $domain) {
            throw ValidationException::withMessages(['domain' => 'Digite só o domínio, como pousadadosol.com.br (sem https:// nem barras).']);
        }
        if (Property::where('domain', $domain)->whereKeyNot($property->id)->exists()) {
            throw ValidationException::withMessages(['domain' => 'Esse domínio já está ligado a outro site.']);
        }

        if ($property->domain && $property->domain !== $domain) {
            $this->disconnect($property);
        }

        $verification = [];
        if ($this->vercelReady()) {
            $verification = $this->addToVercel($domain);
        }

        $property->forceFill([
            'domain' => $domain,
            'settings' => ['domain' => ['status' => 'pending', 'verification' => $verification]] + ($property->settings ?? []),
        ])->save();
        Cache::forget(AllowSiteDomains::CACHE_KEY);

        $this->check($property);
    }

    /**
     * Confere se o DNS já aponta para o site e guarda a situação.
     */
    public function check(Property $property): string
    {
        $domain = $property->domain;
        if (! $domain) {
            return 'none';
        }

        $status = $this->vercelReady() ? $this->vercelStatus($domain) : null;
        $status ??= $this->dnsStatus($domain);

        $settings = $property->settings ?? [];
        $settings['domain'] = ['status' => $status, 'checked_at' => now()->toAtomString(), 'verification' => $settings['domain']['verification'] ?? []];
        $property->forceFill(['settings' => $settings])->save();

        return $status;
    }

    public function disconnect(Property $property): void
    {
        $domain = $property->domain;
        if ($domain && $this->vercelReady()) {
            foreach ($this->hosts($domain) as $host) {
                $this->vercel()->delete($this->projectPath("/domains/{$host}"));
            }
        }

        $settings = $property->settings ?? [];
        unset($settings['domain']);
        $property->forceFill(['domain' => null, 'settings' => $settings ?: null])->save();
        Cache::forget(AllowSiteDomains::CACHE_KEY);
    }

    /**
     * Registros de DNS para apontar o domínio: no domínio raiz, A no @ e CNAME no www; num
     * subdomínio (reservas.pousada.com.br), só o CNAME dele.
     *
     * @return list<array{type: string, name: string, value: string}>
     */
    public function records(string $domain): array
    {
        $root = self::root($domain);

        if ($root === $domain) {
            return [
                ['type' => 'A', 'name' => '@', 'value' => self::A_RECORD],
                ['type' => 'CNAME', 'name' => 'www', 'value' => self::CNAME_TARGET],
            ];
        }

        return [['type' => 'CNAME', 'name' => substr($domain, 0, -strlen($root) - 1), 'value' => self::CNAME_TARGET]];
    }

    /**
     * "https://www.PousadaDoSol.com.br/inicio" → "pousadadosol.com.br" (sem www: o www vira redirecionamento).
     */
    public static function normalize(string $input): ?string
    {
        $value = strtolower(trim($input));
        $value = preg_replace('#^[a-z]+://#', '', $value);
        $value = preg_replace('#[/?\#].*$#', '', $value);
        $value = preg_replace('/^www\./', '', rtrim($value, '.'));

        if (! preg_match('/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/', $value)) {
            return null;
        }
        // Não dá para usar o endereço do próprio Hospedaki.
        if (preg_match('/(^|\.)(vercel\.app|onrender\.com|localhost)$/', $value)) {
            return null;
        }

        return $value;
    }

    /** Domínio raiz: pousada.com.br de reservas.pousada.com.br. */
    public static function root(string $domain): string
    {
        $labels = explode('.', $domain);
        $suffix = implode('.', array_slice($labels, -2));
        $take = in_array($suffix, self::TWO_LEVEL_SUFFIXES, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }

    // ── Conferência pelo DNS (sem a integração com a Vercel) ──

    private function dnsStatus(string $domain): string
    {
        $points = fn (string $host) => in_array(self::A_RECORD, $this->dns->a($host), true)
            || collect($this->dns->cname($host))->contains(fn ($t) => str_ends_with($t, 'vercel-dns.com'));

        if ($points($domain)) {
            return 'active';
        }

        // Tem DNS, mas apontando para outro lugar (a hospedagem antiga, por exemplo).
        return $this->dns->a($domain) || $this->dns->cname($domain) ? 'misconfigured' : 'pending';
    }

    // ── Vercel ──

    private function vercelReady(): bool
    {
        return filled(config('services.vercel.token')) && filled(config('services.vercel.project_id'));
    }

    private function vercel(): PendingRequest
    {
        return Http::baseUrl('https://api.vercel.com')
            ->withToken((string) config('services.vercel.token'))
            ->acceptJson()
            ->timeout(20)
            ->withQueryParameters(array_filter(['teamId' => config('services.vercel.team_id')]));
    }

    private function projectPath(string $path): string
    {
        return '/v10/projects/'.config('services.vercel.project_id').$path;
    }

    /**
     * Domínio raiz e o www (redirecionando para o raiz). Num subdomínio, só ele.
     *
     * @return list<string>
     */
    private function hosts(string $domain): array
    {
        return self::root($domain) === $domain ? [$domain, 'www.'.$domain] : [$domain];
    }

    /**
     * @return list<array{type: string, domain: string, value: string}>
     */
    private function addToVercel(string $domain): array
    {
        $verification = [];

        foreach ($this->hosts($domain) as $host) {
            $response = $this->vercel()->post($this->projectPath('/domains'), array_filter([
                'name' => $host,
                'redirect' => $host === $domain ? null : $domain,
            ]));

            // 409: o domínio já está neste projeto (ligar de novo não é erro).
            if ($response->status() === 409 && ($response->json('error.code') === 'domain_already_in_use' || str_contains((string) $response->json('error.message'), 'already'))) {
                if (($response->json('error.projectId') ?? config('services.vercel.project_id')) !== config('services.vercel.project_id')) {
                    throw ValidationException::withMessages(['domain' => 'Esse domínio está ligado a outro site na Vercel. Tire ele de lá e tente de novo.']);
                }

                continue;
            }
            if ($response->failed()) {
                Log::warning('Vercel recusou o domínio', ['domain' => $host, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 1000)]);

                throw ValidationException::withMessages(['domain' => $response->status() === 400
                    ? 'A Vercel não aceitou esse domínio. Confira se está escrito certo.'
                    : 'Não foi possível ligar o domínio agora. Tente de novo em instantes.']);
            }

            foreach ($response->json('verification') ?? [] as $item) {
                $verification[] = ['type' => $item['type'] ?? 'TXT', 'domain' => $item['domain'] ?? $host, 'value' => $item['value'] ?? ''];
            }
        }

        return $verification;
    }

    /** Situação pela Vercel; null quando ela não respondeu (aí vale a conferência do DNS). */
    private function vercelStatus(string $domain): ?string
    {
        $project = $this->vercel()->get($this->projectPath("/domains/{$domain}"));
        $config = $this->vercel()->get("/v6/domains/{$domain}/config");

        if ($project->failed() || $config->failed()) {
            return null;
        }
        if ($project->json('verified') === false) {
            return 'pending';
        }

        return $config->json('misconfigured') ? ($this->dns->a($domain) || $this->dns->cname($domain) ? 'misconfigured' : 'pending') : 'active';
    }
}
