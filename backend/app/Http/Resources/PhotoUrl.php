<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PhotoUrl
{
    /**
     * Aceita um caminho no disco "public" ou uma URL completa.
     */
    public static function from(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://']) ? $path : Storage::disk('public')->url($path);
    }
}
