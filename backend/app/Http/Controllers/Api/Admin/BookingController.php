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
        $request->validate(['status' => ['nullable', 'string']]);

        $bookings = $request->user()->property->bookings()
            ->with('accommodations')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderBy('check_in')
            ->paginate(30);

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
