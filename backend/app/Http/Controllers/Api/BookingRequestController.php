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
            $this->bookings->quote($property, $data['accommodation_ids'], $data['check_in'], $data['check_out'], $data['guests'])
        );
    }

    public function store(StoreBookingRequest $request, Property $property): JsonResponse
    {
        $booking = $this->bookings->request($property, $request->validated());

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }
}
