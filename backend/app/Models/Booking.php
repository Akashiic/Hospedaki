<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'property_id', 'code', 'status', 'check_in', 'check_out', 'guests',
    'guest_name', 'guest_email', 'guest_phone', 'notes', 'total',
    'confirmed_at', 'cancelled_at',
])]
class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'guests' => 'integer',
            'total' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->code ??= strtoupper(Str::random(8));
        });
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function accommodations(): BelongsToMany
    {
        return $this->belongsToMany(Accommodation::class, 'booking_accommodation')
            ->withPivot('subtotal')
            ->withTimestamps();
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
