<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings, private readonly PaymentService $payments) {}

    /** Filtros da aba Reservas do painel. */
    public const SCOPES = ['requests', 'upcoming', 'ongoing', 'completed', 'past', 'cancelled', 'all'];

    /**
     * Pedidos e reservas da pousada. Sem paginação: o painel lê só "data" e as listas de uma
     * pousada pequena são curtas; com paginate(30), a partir da 31ª elas sumiam sem aviso.
     *
     * Filtros (scope), com "hoje" no fuso da pousada:
     *   requests   pedidos esperando resposta cuja entrada ainda não passou
     *   upcoming   confirmadas que ainda vão começar
     *   ongoing    confirmadas com o hóspede na pousada agora
     *   completed  confirmadas que já terminaram
     *   past       tudo que já terminou, inclusive pedidos que venceram sem resposta
     *   cancelled  recusadas, canceladas e as que não foram pagas no prazo
     * A resposta traz em meta.counts quantas há em cada filtro.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string'],
            'upcoming' => ['nullable', 'boolean'],
            'scope' => ['nullable', Rule::in(self::SCOPES)],
        ]);

        $property = $request->user()->property;
        $today = $property->today()->toDateString();
        $scope = $request->query('scope');
        $this->payments->expireStale($property);

        $bookings = $this->scoped($property->bookings(), $scope, $today)
            ->with('accommodations')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('upcoming'), fn ($query) => $query->whereDate('check_out', '>=', $today))
            // O que está por vir em ordem de chegada; o que já passou, do mais recente para trás.
            ->orderBy('check_in', in_array($scope, ['completed', 'past', 'cancelled', 'all'], true) ? 'desc' : 'asc')
            ->get();

        $counts = collect(self::SCOPES)->mapWithKeys(fn (string $name) => [
            $name => $this->scoped($property->bookings(), $name, $today)->count(),
        ]);

        return BookingResource::collection($bookings)->additional(['meta' => ['counts' => $counts]]);
    }

    private function scoped(HasMany $query, ?string $scope, string $today): HasMany
    {
        $confirmed = BookingStatus::Confirmed;

        // Terminou: a estadia na data de saída; a reserva por hora (que começa e termina no mesmo
        // dia) só no dia seguinte, para aparecer "em andamento" no próprio dia.
        $ended = fn ($q) => $q->where(fn ($q) => $q->whereNull('start_time')->whereDate('check_out', '<=', $today))
            ->orWhere(fn ($q) => $q->whereNotNull('start_time')->whereDate('check_in', '<', $today));

        return match ($scope) {
            'requests' => $query->where('status', BookingStatus::Pending)->whereDate('check_in', '>=', $today),
            'upcoming' => $query->where('status', $confirmed)->whereDate('check_in', '>', $today),
            'ongoing' => $query->where('status', $confirmed)->whereDate('check_in', '<=', $today)->whereNot($ended),
            'completed' => $query->where('status', $confirmed)->where($ended),
            'past' => $query->where(fn ($q) => $q->where($ended)
                ->orWhere(fn ($q) => $q->where('status', BookingStatus::Pending)->whereDate('check_in', '<', $today))),
            'cancelled' => $query->whereIn('status', [BookingStatus::Declined, BookingStatus::Cancelled, BookingStatus::Expired]),
            default => $query,
        };
    }

    public function confirm(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeOwner($request, $booking);

        return new BookingResource($this->bookings->confirm($booking)->load('accommodations'));
    }

    public function decline(Request $request, Booking $booking): BookingResource
    {
        $this->authorizeOwner($request, $booking);

        return new BookingResource($this->bookings->decline($booking)->load('accommodations'));
    }

    private function authorizeOwner(Request $request, Booking $booking): void
    {
        abort_unless($booking->property_id === $request->user()->property_id, 404);
    }
}
