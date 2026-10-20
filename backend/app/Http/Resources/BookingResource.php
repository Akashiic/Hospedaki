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
            // Estadia: horário previsto de chegada. Por hora: início, fim e horas.
            'arrival_time' => Booking::hm($this->arrival_time),
            'start_time' => Booking::hm($this->start_time),
            'end_time' => Booking::hm($this->end_time),
            'hours' => $this->hours(),
            'guests' => $this->guests,
            'guest_name' => $this->guest_name,
            'guest_email' => $this->when($isOwner, $this->guest_email),
            'guest_phone' => $this->when($isOwner, $this->guest_phone),
            'notes' => $this->when($isOwner, $this->notes),
            'total' => $this->total,
            // Pessoas a mais cujo acréscimo não está no total e ainda vai ser combinado.
            'extra_guests_unpriced' => $this->extra_guests_unpriced,
            'accommodations' => $this->whenLoaded('accommodations', fn () => $this->accommodations->map(fn ($accommodation) => [
                'id' => $accommodation->id,
                'name' => $accommodation->name,
                'subtotal' => $accommodation->pivot->subtotal,
            ])),
            // Pagamento online: prazo para pagar, quando e como foi pago e o desconto do Pix.
            'payment_due_at' => $this->when($isOwner, $this->payment_due_at),
            'paid_at' => $this->when($isOwner, $this->paid_at),
            'payment_method' => $this->when($isOwner, $this->payment_method),
            'pix_discount' => $this->when($isOwner, $this->pix_discount),
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
        ];
    }
}
