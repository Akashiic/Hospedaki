<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Property;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Recebe uma foto do painel, endireita pela orientação da câmera, reduz para no máximo 1600 px
 * e regrava em JPEG (sem os metadados do celular, como a localização).
 */
class ImageService
{
    public const MAX_SIDE = 1600;

    public function store(Property $property, UploadedFile $file): Media
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'Não foi possível ler esta imagem. Envie uma foto JPG, PNG ou WebP.']);
        }

        $image = $this->orient($image, $file->getRealPath());
        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, 80);
        $data = (string) ob_get_clean();
        [$width, $height] = [imagesx($image), imagesy($image)];
        imagedestroy($image);

        return Media::create([
            'property_id' => $property->id,
            'mime' => 'image/jpeg',
            'width' => $width,
            'height' => $height,
            'size' => strlen($data),
            'data' => base64_encode($data),
        ]);
    }

    /**
     * Fotos de celular vêm deitadas com uma marca EXIF dizendo como girar.
     */
    private function orient(\GdImage $image, string $path): \GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
