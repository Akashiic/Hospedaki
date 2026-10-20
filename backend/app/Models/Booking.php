<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'property_id', 'code', 'checkout_token', 'status', 'check_in', 'check_out', 'arrival_time', 'start_time', 'end_time', 'guests', 'extra_guests_unpriced',
    'guest_name', 'guest_email', 'guest_phone', 'notes', 'total',
    'confirmed_at', 'cancelled_at', 'payment_due_at', 'paid_at', 'payment_method', 'pix_discount',
])]
class Booking extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'extra_guests_unpriced' => 0,
        'pix_discount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'guests' => 'integer',
            'extra_guests_unpriced' => 'integer',
            'total' => 'integer',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payment_due_at' => 'datetime',
            'paid_at' => 'datetime',
            'pix_discount' => 'integer',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Reservas que ocupam o calendário: pedidos pendentes e confirmadas, e as que esperam o
     * pagamento enquanto o prazo não passou (as vencidas viram Expired depois, mas já não seguram).
     */
    public function scopeHoldingDates(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->orWhere(fn (Builder $q) => $q->where('status', BookingStatus::AwaitingPayment)->where('payment_due_at', '>', now())));
    }

    /** Esperando o pagamento e ainda dentro do prazo. */
    public function awaitsPayment(): bool
    {
        return $this->status === BookingStatus::AwaitingPayment && $this->payment_due_at?->isFuture();
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    /** Reserva por hora: tem início e fim no mesmo dia, em vez de noites. */
    public function isHourly(): bool
    {
        return $this->start_time !== null;
    }

    /** Horas reservadas (0 numa reserva por diária). */
    public function hours(): int
    {
        return $this->isHourly() ? intdiv(self::minutes($this->end_time) - self::minutes($this->start_time), 60) : 0;
    }

    /** "16:30:00" → 990. */
    public static function minutes(?string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', (string) $time) + [0, 0]);

        return $h * 60 + $m;
    }

    /** Horário sem os segundos ("16:30"), ou null. */
    public static function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
