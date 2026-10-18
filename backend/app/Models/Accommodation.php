<?php

namespace App\Models;

use Database\Factories\AccommodationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id', 'name', 'slug', 'description', 'amenities', 'max_guests', 'included_guests', 'base_price',
    'weekend_price', 'extra_guest_price', 'min_nights', 'sort_order', 'active',
])]
class Accommodation extends Model
{
    /** @use HasFactory<AccommodationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'max_guests' => 'integer',
            'included_guests' => 'integer',
            'base_price' => 'integer',
            'weekend_price' => 'integer',
            'extra_guest_price' => 'integer',
            'min_nights' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AccommodationPhoto::class)->orderBy('sort_order');
    }

    public function seasonalPrices(): HasMany
    {
        return $this->hasMany(SeasonalPrice::class);
    }

    public function blockedDates(): HasMany
    {
        return $this->hasMany(BlockedDate::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_accommodation')
            ->withPivot('subtotal')
            ->withTimestamps();
    }
}
