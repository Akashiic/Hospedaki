<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto da pousada. As marcadas como destaque (featured) aparecem no carrossel da capa.
 */
#[Fillable(['property_id', 'path', 'caption', 'featured', 'sort_order'])]
class PropertyPhoto extends Model
{
    protected function casts(): array
    {
        return ['featured' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
