<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemoService;
use App\Services\DomainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Seu site → Domínio": ligar, conferir e desligar o domínio próprio do site.
 */
class DomainController extends Controller
{
    public function __construct(private readonly DomainService $domains) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->domains->state($request->user()->property));
    }

    public function update(Request $request, DemoService $demo): JsonResponse
    {
        $property = $request->user()->property;
        // Na demonstração o painel é público: um visitante não pode apontar o site para um domínio.
        if ($demo->isDemo($property)) {
            throw ValidationException::withMessages(['domain' => 'Na demonstração o domínio fica travado. No site da sua pousada você liga o seu.']);
        }

        $data = $request->validate(['domain' => ['required', 'string', 'max:255']], ['domain.required' => 'Digite o domínio.']);
        $this->domains->connect($property, $data['domain']);

        return response()->json($this->domains->state($property->fresh()));
    }

    public function check(Request $request): JsonResponse
    {
        $property = $request->user()->property;
        $this->domains->check($property);

        return response()->json($this->domains->state($property->fresh()));
    }

    public function destroy(Request $request, DemoService $demo): JsonResponse
    {
        $property = $request->user()->property;
        abort_if($demo->isDemo($property), 422, 'Na demonstração o domínio fica travado.');
        $this->domains->disconnect($property);

        return response()->json($this->domains->state($property->fresh()));
    }
}
