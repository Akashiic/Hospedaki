<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Apaga os tokens do painel vencidos há mais de um dia (ver expiration em config/sanctum.php).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
