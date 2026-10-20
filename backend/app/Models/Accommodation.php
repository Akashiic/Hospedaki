<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AccommodationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'property_id', 'name', 'slug', 'shared_unit', 'best_for', 'description', 'amenities', 'details', 'max_guests', 'included_guests', 'base_price',
    'weekend_price', 'extra_guest_price', 'cleaning_fee', 'weekly_discount', 'monthly_discount', 'min_nights', 'sort_order', 'active',
    'booking_mode', 'check_in_time', 'check_out_time', 'hourly_price', 'min_hours', 'opens_at', 'closes_at',
    'accepts_pix', 'accepts_card', 'pix_discount', 'promo_ends_at', 'promo_loop', 'promo_loop_hours',
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

    /** Tipos da ficha para o Google (viram o @type do Schema.org no site). */
    public const KINDS = ['room', 'suite', 'chalet', 'house', 'apartment', 'camping', 'venue'];

    /** Camas da ficha (BedDetails.typeOfBed). */
    public const BED_TYPES = ['king', 'queen', 'double', 'single', 'sofa_bed', 'bunk', 'crib'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'booking_mode' => self::NIGHTLY,
        'min_hours' => 1,
        'accepts_pix' => true,
        'accepts_card' => true,
        'pix_discount' => 0,
        'promo_loop' => false,
        'promo_loop_hours' => 24,
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'details' => 'array',
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
            'accepts_pix' => 'boolean',
            'accepts_card' => 'boolean',
            'pix_discount' => 'integer',
            'promo_ends_at' => 'immutable_datetime',
            'promo_loop' => 'boolean',
            'promo_loop_hours' => 'integer',
        ];
    }

    /**
     * Ficha para o Google sem campos vazios: camas iguais somadas, nada preenchido = null.
     *
     * @param  array<string, mixed>|null  $details
     * @return array{kind?: string, bedrooms?: int, bathrooms?: int, area?: int, beds?: list<array{type: string, count: int}>, pets?: bool, smoking?: bool}|null
     */
    public static function normalizeDetails(?array $details): ?array
    {
        if (! $details) {
            return null;
        }

        $beds = [];
        foreach ($details['beds'] ?? [] as $bed) {
            $type = (string) ($bed['type'] ?? '');
            $count = (int) ($bed['count'] ?? 0);
            if (in_array($type, self::BED_TYPES, true) && $count > 0) {
                $beds[$type] = ($beds[$type] ?? 0) + $count;
            }
        }

        $clean = array_filter([
            'kind' => in_array($details['kind'] ?? null, self::KINDS, true) ? $details['kind'] : null,
            'bedrooms' => isset($details['bedrooms']) ? (int) $details['bedrooms'] : null,
            'bathrooms' => isset($details['bathrooms']) ? (int) $details['bathrooms'] : null,
            'area' => isset($details['area']) ? (int) $details['area'] : null,
            'beds' => array_map(fn ($type, $count) => ['type' => $type, 'count' => $count], array_keys($beds), $beds) ?: null,
            'pets' => isset($details['pets']) ? (bool) $details['pets'] : null,
            'smoking' => isset($details['smoking']) ? (bool) $details['smoking'] : null,
        ], fn ($value) => $value !== null);

        return $clean ?: null;
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

    /**
     * Desconto no Pix valendo agora: o percentual e quando a promoção acaba (null = sem prazo).
     *
     * Com prazo, o desconto some quando ele passa. Com o timer em loop, a promoção recomeça na
     * hora em que acaba e dura promo_loop_hours de novo; o fim mostrado é o do ciclo atual.
     *
     * @return array{percent: int, ends_at: ?CarbonImmutable, loop: bool}|null
     */
    public function pixPromo(?CarbonInterface $now = null): ?array
    {
        if (! $this->accepts_pix || $this->pix_discount <= 0) {
            return null;
        }

        $now = CarbonImmutable::parse($now ?? now());
        $ends = $this->promo_ends_at;

        if ($ends && $ends->lte($now)) {
            if (! $this->promo_loop) {
                return null;
            }
            $cycle = max(1, $this->promo_loop_hours) * 3600;
            $ends = $ends->addSeconds((intdiv((int) $ends->diffInSeconds($now), $cycle) + 1) * $cycle);
        }

        return ['percent' => $this->pix_discount, 'ends_at' => $ends, 'loop' => $this->promo_loop && $ends !== null];
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
