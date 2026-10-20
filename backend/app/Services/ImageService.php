<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Property;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Recebe uma foto do painel, endireita pela orientação da câmera, reduz para no máximo 1600 px
 * e regrava em JPEG (sem os metadados do celular, como a localização). Logo, ícone e imagem de
 * compartilhamento do site têm medidas próprias (storeSiteImage).
 */
class ImageService
{
    public const MAX_SIDE = 1600;

    public function store(Property $property, UploadedFile $file): Media
    {
        $image = $this->read($file);
        $image = $this->fit($image, self::MAX_SIDE, self::MAX_SIDE);

        return $this->save($property, $image, 'jpeg');
    }

    /**
     * Imagens do site, cada uma no formato em que é usada:
     *  - logo: até 800 × 240, em PNG para manter o fundo transparente;
     *  - icon: quadrado de 512 px (recortado no centro), PNG; o navegador reduz para a aba;
     *  - share: 1200 × 630 (recortado no centro), a medida que WhatsApp e redes sociais mostram.
     */
    public function storeSiteImage(Property $property, UploadedFile $file, string $kind): Media
    {
        $image = $this->read($file);

        return match ($kind) {
            'logo' => $this->save($property, $this->fit($image, 800, 240), 'png'),
            'icon' => $this->save($property, $this->cover($image, 512, 512), 'png'),
            'share' => $this->save($property, $this->cover($image, 1200, 630), 'jpeg'),
        };
    }

    private function read(UploadedFile $file): \GdImage
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'Não foi possível ler esta imagem. Envie uma foto JPG, PNG ou WebP.']);
        }

        return $this->orient($image, $file->getRealPath());
    }

    /** Reduz para caber em largura × altura, sem cortar e sem aumentar. */
    private function fit(\GdImage $image, int $maxWidth, int $maxHeight): \GdImage
    {
        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);

        return $scale < 1 ? $this->resample($image, 0, 0, $width, $height, (int) round($width * $scale), (int) round($height * $scale)) : $image;
    }

    /** Preenche largura × altura recortando o excesso no centro, como object-fit: cover. */
    private function cover(\GdImage $image, int $width, int $height): \GdImage
    {
        [$srcWidth, $srcHeight] = [imagesx($image), imagesy($image)];
        $scale = max($width / $srcWidth, $height / $srcHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);

        return $this->resample($image, intdiv($srcWidth - $cropWidth, 2), intdiv($srcHeight - $cropHeight, 2), $cropWidth, $cropHeight, $width, $height);
    }

    /** Copia um trecho da imagem para uma tela nova do tamanho pedido, mantendo a transparência. */
    private function resample(\GdImage $image, int $x, int $y, int $srcWidth, int $srcHeight, int $width, int $height): \GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, $x, $y, $width, $height, $srcWidth, $srcHeight);
        imagedestroy($image);

        return $canvas;
    }

    /** Grava a imagem no banco (JPEG sem transparência, PNG com). */
    private function save(Property $property, \GdImage $image, string $format): Media
    {
        ob_start();
        if ($format === 'png') {
            imagesavealpha($image, true);
            imagepng($image, null, 9);
        } else {
            imageinterlace($image, true);
            imagejpeg($this->flatten($image), null, 80);
        }
        $data = (string) ob_get_clean();
        [$width, $height] = [imagesx($image), imagesy($image)];

        return Media::create([
            'property_id' => $property->id,
            'mime' => "image/{$format}",
            'width' => $width,
            'height' => $height,
            'size' => strlen($data),
            'data' => base64_encode($data),
        ]);
    }

    /** PNG transparente salvo como JPEG ficaria com fundo preto: o fundo vira branco. */
    private function flatten(\GdImage $image): \GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
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
