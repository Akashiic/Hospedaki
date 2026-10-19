<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Response;

/**
 * Entrega as fotos enviadas pelo painel. O conteúdo de uma foto nunca muda (trocar é enviar
 * outra), então o navegador e a CDN podem guardar por um ano.
 */
class MediaController extends Controller
{
    public function show(Media $media): Response
    {
        $data = (string) base64_decode($media->data);

        return response($data, 200, [
            'Content-Type' => $media->mime,
            'Content-Length' => (string) strlen($data),
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
