<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlockedDateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocks = BlockedDate::query()
            ->whereIn('accommodation_id', $request->user()->property->accommodations()->select('id'))
            ->orderBy('starts_on')
            ->get();

        return response()->json(['data' => $blocks]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accommodation_id' => ['required', 'integer', Rule::exists('accommodations', 'id')->where('property_id', $request->user()->property_id)],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => BlockedDate::create($data)], 201);
    }

    public function destroy(Request $request, BlockedDate $blockedDate): JsonResponse
    {
        abort_unless($blockedDate->accommodation->property_id === $request->user()->property_id, 404);

        $blockedDate->delete();

        return response()->json(status: 204);
    }
}
