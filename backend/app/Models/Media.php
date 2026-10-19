<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Foto enviada pelo painel, guardada no banco. Fotos de acomodação apontam para ela com o
 * caminho "media:{id}" (ver PhotoUrl).
 */
#[Fillable(['property_id', 'mime', 'width', 'height', 'size', 'data'])]
#[Hidden(['data'])]
class Media extends Model
{
    public const PATH_PREFIX = 'media:';

    public function path(): string
    {
        return self::PATH_PREFIX.$this->id;
    }

    /**
     * Id da foto guardada no banco a partir do caminho salvo, ou null quando é outro tipo de foto.
     */
    public static function idFromPath(?string $path): ?int
    {
        return $path && str_starts_with($path, self::PATH_PREFIX) ? (int) substr($path, strlen(self::PATH_PREFIX)) : null;
    }
}
