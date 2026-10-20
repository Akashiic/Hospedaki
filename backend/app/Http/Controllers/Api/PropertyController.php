<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\AvailabilityService;
use App\Services\DemoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function show(Property $property, DemoService $demo): PropertyResource
    {
        // Toda visita ao site começa por aqui: é a hora de arrumar a demo do dia.
        $demo->resetIfStale($property);

        $property->load(['photos', 'accommodations' => fn ($query) => $query->where('active', true)->with('photos')]);

        return new PropertyResource($property);
    }

    /**
     * Noites indisponíveis de cada acomodação, para montar o calendário do site.
     */
    public function availability(Request $request, Property $property, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to'])->min($from->addDays(370));

        $accommodations = $property->accommodations()->where('active', true)->get();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            // Por diária: as noites ocupadas. Por hora: os dias em que não cabe mais nenhuma reserva.
            'accommodations' => $accommodations->map(fn ($accommodation) => [
                'id' => $accommodation->id,
                'unavailable' => $accommodation->isHourly()
                    ? $availability->unavailableHourDays($accommodation, $from, $to)
                    : $availability->unavailableNights($accommodation, $from, $to),
            ]),
        ]);
    }

    /**
     * Horários de um dia para uma acomodação reservada por hora: funcionamento e faixas já reservadas.
     */
    public function hours(Request $request, Property $property, AvailabilityService $availability): JsonResponse
    {
        $validated = $request->validate([
            'accommodation_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $accommodation = $property->accommodations()->where('active', true)->findOrFail($validated['accommodation_id']);
        abort_unless($accommodation->isHourly(), 404);
        $date = CarbonImmutable::parse($validated['date']);
        $now = CarbonImmutable::now($property->timezone ?: Property::DEFAULT_TIMEZONE);

        return response()->json([
            'date' => $date->toDateString(),
            'opens_at' => $accommodation->opensAt(),
            'closes_at' => $accommodation->closesAt(),
            'min_hours' => $accommodation->min_hours,
            'closed' => $date->lt($property->today()) || $availability->dayClosedForHours($accommodation, $date),
            'taken' => $availability->takenHours($accommodation, $date),
            // Hoje, os horários que já passaram não podem ser escolhidos.
            'not_before' => $date->isSameDay($property->today()) ? $now->format('H:i') : null,
        ]);
    }
}
