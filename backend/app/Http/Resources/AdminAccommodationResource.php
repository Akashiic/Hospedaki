<?php

namespace App\Http\Resources;

use App\Models\Accommodation;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Acomodação como o painel precisa para editar: tudo do site, mais o que só o dono vê
 * (publicada ou não, ordem, ids das fotos, temporadas e quantas reservas tem).
 *
 * @mixin Accommodation
 */
class AdminAccommodationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // array_merge: as fotos daqui (com id) substituem as do site.
        return array_merge((new AccommodationResource($this->resource))->toArray($request), [
            'active' => $this->active,
            // Horários próprios (vazio = os da pousada, que vão de exemplo no formulário).
            'check_in_time' => Booking::hm($this->check_in_time),
            'check_out_time' => Booking::hm($this->check_out_time),
            'property_check_in_time' => substr((string) $this->property->check_in_time, 0, 5),
            'property_check_out_time' => substr((string) $this->property->check_out_time, 0, 5),
            'sort_order' => $this->sort_order,
            // Pagamento: o que o dono configurou (o "payment" do site mostra o que vale agora).
            'accepts_pix' => $this->accepts_pix,
            'accepts_card' => $this->accepts_card,
            'pix_discount' => $this->pix_discount,
            'promo_ends_at' => $this->promo_ends_at?->toAtomString(),
            'promo_loop' => $this->promo_loop,
            'promo_loop_hours' => $this->promo_loop_hours,
            'photos' => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => PhotoUrl::from($photo->path),
                'caption' => $photo->caption,
            ]),
            'seasons' => $this->seasonalPrices->map(fn ($season) => [
                'id' => $season->id,
                'name' => $season->name,
                'starts_on' => $season->starts_on->toDateString(),
                'ends_on' => $season->ends_on->toDateString(),
                'price' => $season->price,
                'weekend_price' => $season->weekend_price,
                'min_nights' => $season->min_nights,
            ]),
            // Com reservas no histórico não dá para excluir, só ocultar.
            'bookings_count' => (int) ($this->bookings_count ?? 0),
            'upcoming_bookings_count' => (int) ($this->upcoming_bookings_count ?? 0),
        ]);
    }
}
