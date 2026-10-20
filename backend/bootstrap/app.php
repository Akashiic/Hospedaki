<?php

use App\Http\Middleware\AllowSiteDomains;
use App\Http\Middleware\NoIndex;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // No Render o pedido chega por um proxy: sem isto o limite por IP valeria para todos juntos.
        $middleware->trustProxies(at: '*');
        $middleware->append(NoIndex::class);
        // Antes do CORS: o domínio próprio de uma pousada também pode chamar a API.
        $middleware->prepend(AllowSiteDomains::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // O site mostra ao hóspede a "message" da API; as genéricas do Laravel vêm em inglês.
        // Com APP_DEBUG ligado, o erro 500 mantém a mensagem original para quem está depurando.
        $exceptions->respond(function ($response) {
            $messages = [
                401 => 'Sua sessão expirou. Entre de novo.',
                403 => 'Acesso negado.',
                404 => 'Não encontrado.',
                405 => 'Método não permitido.',
                429 => 'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.',
                500 => 'Algo deu errado do nosso lado. Tente novamente em instantes.',
                503 => 'Estamos em manutenção. Tente de novo em instantes.',
            ];
            $status = $response->getStatusCode();

            if ($response instanceof JsonResponse && isset($messages[$status]) && ! ($status === 500 && config('app.debug'))) {
                $response->setData(['message' => $messages[$status]] + $response->getData(true));
            }

            return $response;
        });
    })->create();
