<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function show(Property $property): PropertyResource
    {
        $property->load(['accommodations' => fn ($query) => $query->where('active', true)->with('photos')]);

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
            'accommodations' => $accommodations->map(fn ($accommodation) => [
                'id' => $accommodation->id,
                'unavailable' => $availability->unavailableNights($accommodation, $from, $to),
            ]),
        ]);
    }
}
