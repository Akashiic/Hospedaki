<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A API tem endereço próprio (hospedaki-api.onrender.com) e não é página para ninguém achar
 * no Google: toda resposta pede aos buscadores para não indexar nem seguir links. As fotos
 * enviadas pelo painel (/api/media) ficam de fora: são as fotos da pousada no Google Imagens.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/media/*')) {
            return $response;
        }

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
