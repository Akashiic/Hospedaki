<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Aba Estatísticas do painel: receita, ocupação e o comportamento dos hóspedes num período.
 */
class StatsController extends Controller
{
    public function __invoke(Request $request, StatsService $stats): JsonResponse
    {
        $data = $request->validate(['period' => ['nullable', Rule::in(StatsService::PERIODS)]]);

        return response()->json($stats->summary($request->user()->property, $data['period'] ?? '30d'));
    }
}
