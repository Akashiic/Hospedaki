<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = $request->user() !== null;

        return [
            'id' => $this->when($isOwner, $this->id),
            'code' => $this->code,
            'status' => $this->status,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->nights(),
            'guests' => $this->guests,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->when($isOwner, $this->guest_email),
            'guest_phone' => $this->when($isOwner, $this->guest_phone),
            'notes' => $this->when($isOwner, $this->notes),
            'total' => $this->total,
            'accommodations' => $this->whenLoaded('accommodations', fn () => $this->accommodations->map(fn ($accommodation) => [
                'id' => $accommodation->id,
                'name' => $accommodation->name,
                'subtotal' => $accommodation->pivot->subtotal,
            ])),
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
        ];
    }
}
