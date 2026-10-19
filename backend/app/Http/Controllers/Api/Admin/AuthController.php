<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\User;
use App\Services\DemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->property_id || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha inválidos.']);
        }

        return response()->json([
            'token' => $user->createToken('painel')->plainTextToken,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /**
     * Login aberto da pousada de demonstração: sem senha, só existe com DEMO_MODE ligado.
     */
    public function demoLogin(DemoService $demo): JsonResponse
    {
        $property = $demo->property();
        abort_unless($property, 404);

        $demo->resetIfStale($property);
        $user = $demo->user($property);

        return response()->json([
            'token' => $user->createToken('painel-demo')->plainTextToken,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }

    /**
     * Usuário logado e a pousada que ele administra.
     */
    public function me(Request $request, DemoService $demo): JsonResponse
    {
        $user = $request->user()->load('property.accommodations');

        return response()->json([
            'user' => ['name' => $user->name, 'email' => $user->email],
            'property' => [
                'name' => $user->property->name,
                'slug' => $user->property->slug,
                'primary_color' => $user->property->primary_color,
                'demo' => $demo->isDemo($user->property),
                // Fuso da pousada: o painel calcula "hoje" igual à API (reserva em andamento, concluída...).
                'timezone' => $user->property->timezone ?: Property::DEFAULT_TIMEZONE,
                'accommodations' => $user->property->accommodations->map->only(['id', 'name']),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }
}
