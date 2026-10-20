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
    'property_id', 'name', 'slug', 'shared_unit', 'best_for', 'description', 'amenities', 'max_guests', 'included_guests', 'base_price',
    'weekend_price', 'extra_guest_price', 'cleaning_fee', 'weekly_discount', 'monthly_discount', 'min_nights', 'sort_order', 'active',
    'booking_mode', 'check_in_time', 'check_out_time', 'hourly_price', 'min_hours', 'opens_at', 'closes_at',
])]
class Accommodation extends Model
{
    /** @use HasFactory<AccommodationFactory> */
    use HasFactory;

    /** Reservada por diária (estadia) ou por hora (day use, eventos). */
    public const NIGHTLY = 'nightly';

    public const HOURLY = 'hourly';

    /** Horário de funcionamento padrão de quem é reservado por hora. */
    public const DEFAULT_OPENS_AT = '08:00';

    public const DEFAULT_CLOSES_AT = '22:00';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'booking_mode' => self::NIGHTLY,
        'min_hours' => 1,
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'max_guests' => 'integer',
            'included_guests' => 'integer',
            'base_price' => 'integer',
            'weekend_price' => 'integer',
            'extra_guest_price' => 'integer',
            'cleaning_fee' => 'integer',
            'weekly_discount' => 'integer',
            'monthly_discount' => 'integer',
            'min_nights' => 'integer',
            'hourly_price' => 'integer',
            'min_hours' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function isHourly(): bool
    {
        return $this->booking_mode === self::HOURLY;
    }

    /** Check-in desta acomodação ("15:00"); sem horário próprio, o da pousada. */
    public function checkInTime(): string
    {
        return substr((string) ($this->check_in_time ?? $this->property->check_in_time), 0, 5);
    }

    public function checkOutTime(): string
    {
        return substr((string) ($this->check_out_time ?? $this->property->check_out_time), 0, 5);
    }

    /** Janela em que pode ser reservada por hora ("08:00" a "22:00"). */
    public function opensAt(): string
    {
        return substr((string) ($this->opens_at ?? self::DEFAULT_OPENS_AT), 0, 5);
    }

    public function closesAt(): string
    {
        return substr((string) ($this->closes_at ?? self::DEFAULT_CLOSES_AT), 0, 5);
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

    /**
     * Ids desta acomodação e das que dividem o mesmo imóvel com ela (shared_unit):
     * uma reserva ou um bloqueio em qualquer uma delas ocupa todas.
     *
     * @return list<int>
     */
    public function unitIds(): array
    {
        if (! $this->shared_unit) {
            return [$this->id];
        }

        return static::where('property_id', $this->property_id)
            ->where('shared_unit', $this->shared_unit)
            ->pluck('id')
            ->all();
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_accommodation')
            ->withPivot('subtotal')
            ->withTimestamps();
    }
}
