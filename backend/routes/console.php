<?php

use App\Services\DemoService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Apaga os tokens do painel vencidos há mais de um dia (ver expiration em config/sanctum.php).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Volta a pousada de demonstração ao estado inicial na hora (normalmente isso acontece sozinho uma vez por dia).
Artisan::command('demo:reset', function (DemoService $demo) {
    $property = $demo->property();

    if (! $property) {
        $this->error('Modo demonstração desligado (DEMO_MODE) ou pousada não encontrada.');

        return 1;
    }

    $demo->reset($property);
    $this->info("Demonstração de {$property->name} reiniciada.");
})->purpose('Reinicia os pedidos e bloqueios da pousada de demonstração');
