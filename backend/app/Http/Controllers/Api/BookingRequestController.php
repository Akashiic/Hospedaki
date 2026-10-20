<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Property;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;

class BookingRequestController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function quote(QuoteRequest $request, Property $property): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->bookings->quote($property, $data['accommodation_ids'], $data['check_in'], $data['check_out'] ?? null, $data['guests'], $data['start_time'] ?? null, $data['end_time'] ?? null)
        );
    }

    public function store(StoreBookingRequest $request, Property $property): JsonResponse
    {
        $booking = $this->bookings->request($property, $request->validated());

        // Com pagamento online, o site leva o hóspede para o checkout com este token.
        return (new BookingResource($booking))
            ->additional(['checkout' => $booking->checkout_token ? ['token' => $booking->checkout_token, 'due_at' => $booking->payment_due_at->toAtomString()] : null])
            ->response()->setStatusCode(201);
    }
}
