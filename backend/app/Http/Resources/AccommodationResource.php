<?php

namespace App\Http\Resources;

use App\Models\Accommodation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Accommodation */
class AccommodationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // Indicação da pousada para a escolha "a dois / com a família" da capa.
            'best_for' => $this->best_for,
            // Acomodações com o mesmo valor ficam no mesmo imóvel e não são reservadas juntas.
            'shared_unit' => $this->shared_unit,
            'amenities' => $this->amenities ?? [],
            'max_guests' => $this->max_guests,
            // Pessoas incluídas na diária e acréscimo por pessoa a mais, por diária (centavos); nulo = não informado.
            'included_guests' => $this->included_guests,
            'base_price' => $this->base_price,
            'weekend_price' => $this->weekend_price,
            'extra_guest_price' => $this->extra_guest_price,
            'min_nights' => $this->min_nights,
            // Taxa de limpeza por estadia (centavos) e descontos (%) para 7 e 28 noites ou mais.
            'cleaning_fee' => $this->cleaning_fee,
            'weekly_discount' => $this->weekly_discount,
            'monthly_discount' => $this->monthly_discount,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'url' => PhotoUrl::from($photo->path),
                'caption' => $photo->caption,
            ])),
        ];
    }
}
