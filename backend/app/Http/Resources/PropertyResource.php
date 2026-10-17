<?php

namespace App\Http\Resources;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Property */
class PropertyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'slug' => $this->slug,
            'description' => $this->description,
            'highlights' => $this->highlights ?? [],
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'url' => PhotoUrl::from($photo->path),
                'caption' => $photo->caption,
                'featured' => $photo->featured,
            ])),
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'logo_url' => PhotoUrl::from($this->logo_path),
            'primary_color' => $this->primary_color,
            'check_in_time' => substr((string) $this->check_in_time, 0, 5),
            'check_out_time' => substr((string) $this->check_out_time, 0, 5),
            'accommodations' => AccommodationResource::collection($this->whenLoaded('accommodations')),
        ];
    }
}
