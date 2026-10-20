<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'tagline', 'slug', 'domain', 'description', 'highlights', 'story', 'story_title', 'extras', 'email', 'phone', 'whatsapp',
    'address', 'city', 'state', 'logo_path', 'primary_color', 'timezone',
    'check_in_time', 'check_out_time', 'settings', 'payment_credentials', 'active',
])]
// Chaves dos gateways de pagamento: nunca saem inteiras numa resposta.
#[Hidden(['payment_credentials'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    public const DEFAULT_TIMEZONE = 'America/Sao_Paulo';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => self::DEFAULT_TIMEZONE,
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'highlights' => 'array',
            'story' => 'array',
            'extras' => 'array',
            'payment_credentials' => 'encrypted:array',
            'active' => 'boolean',
        ];
    }

    /**
     * Pousadas desativadas não aparecem no site público.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)->where('active', true)->first();
    }

    /**
     * Data de hoje no fuso da pousada. O servidor roda em UTC, e às 21h em Brasília lá já é amanhã.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone ?: self::DEFAULT_TIMEZONE)->startOfDay();
    }

    public function accommodations(): HasMany
    {
        return $this->hasMany(Accommodation::class)->orderBy('sort_order')->orderBy('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
