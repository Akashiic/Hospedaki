<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PhotoUrl
{
    /**
     * Aceita um caminho no disco "public", uma URL completa ou um caminho começando com "/",
     * que é uma foto servida pelo próprio site (frontend/public) e segue sem mudança.
     */
    public static function from(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : Storage::disk('public')->url($path);
    }
}
