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
            'amenities' => $this->amenities ?? [],
            'max_guests' => $this->max_guests,
            // Pessoas incluídas na diária e acréscimo por pessoa a mais, por diária (centavos); nulo = não informado.
            'included_guests' => $this->included_guests,
            'base_price' => $this->base_price,
            'weekend_price' => $this->weekend_price,
            'extra_guest_price' => $this->extra_guest_price,
            'min_nights' => $this->min_nights,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'url' => PhotoUrl::from($photo->path),
                'caption' => $photo->caption,
            ])),
        ];
    }
}
