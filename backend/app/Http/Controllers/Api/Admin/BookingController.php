<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', 'string'], 'upcoming' => ['nullable', 'boolean']]);

        $property = $request->user()->property;

        $bookings = $property->bookings()
            ->with('accommodations')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->boolean('upcoming'), fn ($query) => $query->whereDate('check_out', '>=', $property->today()->toDateString()))
            ->orderBy('check_in')
            // Sem paginação: o painel pede listas curtas (pendentes e próximas) e lê só "data";
            // com paginate(30), a partir da 31ª reserva elas sumiam do painel sem aviso.
            ->get();

        return BookingResource::collection($bookings);
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
