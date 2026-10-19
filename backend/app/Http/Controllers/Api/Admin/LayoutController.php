<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\HomeLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ordem e visibilidade das seções da página inicial, escolhidas pelo dono no painel.
 */
class LayoutController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'sections' => HomeLayout::normalize($request->user()->property->settings['home_sections'] ?? null),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array', 'max:'.count(HomeLayout::SECTIONS)],
            'sections.*.key' => ['required', 'string', 'distinct', Rule::in(HomeLayout::SECTIONS)],
            'sections.*.visible' => ['required', 'boolean'],
        ]);

        $property = $request->user()->property;
        $sections = HomeLayout::normalize($data['sections']);
        $property->update(['settings' => ['home_sections' => $sections] + ($property->settings ?? [])]);

        return response()->json(['sections' => $sections]);
    }
}
