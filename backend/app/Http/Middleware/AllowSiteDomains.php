<?php

namespace App\Http\Middleware;

use App\Models\Property;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Libera no CORS os domínios próprios das pousadas (painel → Domínio): o site no endereço novo
 * consulta a API pelo navegador (disponibilidade, orçamento, checkout).
 */
class AllowSiteDomains
{
    public const CACHE_KEY = 'site-domains';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin');

        if ($origin !== '' && $request->is('api/*') && str_starts_with($origin, 'https://')) {
            $allowed = config('cors.allowed_origins', []);
            $host = strtolower((string) parse_url($origin, PHP_URL_HOST));

            if (! in_array($origin, $allowed, true) && in_array($host, self::domains(), true)) {
                config(['cors.allowed_origins' => [...$allowed, $origin]]);
            }
        }

        return $next($request);
    }

    /**
     * Domínios cadastrados (com e sem www), guardados por alguns minutos.
     *
     * @return list<string>
     */
    public static function domains(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => Property::whereNotNull('domain')->pluck('domain')
            ->flatMap(fn (string $domain) => [$domain, 'www.'.$domain])->values()->all());
    }
}
