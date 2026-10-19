<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PhotoUrl
{
    /**
     * Aceita um caminho no disco "public", uma URL completa ou um caminho começando com "/",
     * que é uma foto servida pelo próprio site (frontend/public) e segue sem mudança, ou "media:{id}",
     * uma foto enviada pelo painel.
     */
    public static function from(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Foto enviada pelo painel e guardada no banco (ver Media).
        if ($id = Media::idFromPath($path)) {
            return url("/api/media/{$id}");
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : Storage::disk('public')->url($path);
    }
}
