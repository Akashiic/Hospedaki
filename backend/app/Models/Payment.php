<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma tentativa de pagamento de uma reserva: um Pix gerado ou um cartão passado, num gateway.
 * Valores em centavos. data guarda o que o checkout mostra (copia e cola do Pix, client_secret
 * do cartão na Stripe, motivo de recusa) e nunca dados do cartão.
 */
#[Fillable(['booking_id', 'provider', 'method', 'status', 'amount', 'discount', 'external_id', 'data', 'expires_at', 'paid_at'])]
class Payment extends Model
{
    public const PIX = 'pix';

    public const CARD = 'card';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const REFUNDED = 'refunded';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::PENDING,
        'discount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'discount' => 'integer',
            'data' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING && ! $this->expires_at?->isPast();
    }

    /** Referência que vai ao gateway e volta no webhook. */
    public function reference(): string
    {
        return 'hospedaki-'.$this->id;
    }
}
