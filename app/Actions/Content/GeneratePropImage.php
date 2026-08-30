<?php

namespace App\Actions\Content;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Una foto decorativa a juego con un color, generada por IA: el limón para
 * "Butter Yellow", la fresa para "Strawberry Red" de las referencias de
 * Mimosa Studio que ella trajo —«agarra la foto, la pone alrededor, pone
 * algo en el medio, el fondo lo pone de un color»—.
 *
 * No es una excepción a "la IA no toca el trabajo" (ver ReadPhotoColor,
 * BuildColorBlock): esto NUNCA genera ni retoca una foto de uñas, cabello o
 * lo que sea el trabajo. Genera un objeto aparte —una fruta, una tela, un
 * accesorio— que solo decora alrededor. El trabajo real sigue siendo
 * siempre una foto de verdad.
 *
 * Se llama una sola vez por color del catálogo (ver FindOrCreateColorProp),
 * nunca por post: es la pieza cara de esta plantilla, y cachearla es lo que
 * la hace viable.
 */
class GeneratePropImage
{
    public function handle(string $colorName, string $colorHex): ?string
    {
        $config = config('services.assistant');
        $key = $config['api_key'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(60)
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    'model' => $config['image_model'] ?? 'google/gemini-2.5-flash-image',
                    'modalities' => ['image', 'text'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => $this->prompt($colorName, $colorHex),
                    ]],
                ]);
        } catch (ConnectionException) {
            // Sin la foto decorativa el corner se deja como fondo liso: se
            // ve más simple, nunca rompe el post.
            return null;
        }

        if ($response->failed()) {
            Log::warning('No se pudo generar la foto decorativa.', [
                'status' => $response->status(),
                'color' => $colorName,
            ]);

            return null;
        }

        $url = $response->json('choices.0.message.images.0.image_url.url');

        if (! is_string($url) || ! str_starts_with($url, 'data:')) {
            return null;
        }

        $comma = strpos($url, ',');

        if ($comma === false) {
            return null;
        }

        $decoded = base64_decode(substr($url, $comma + 1), true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    private function prompt(string $colorName, string $colorHex): string
    {
        return <<<TXT
        Fotografía de producto de UN SOLO objeto pequeño y elegante —una fruta,
        una flor, un dulce, una tela, un accesorio— cuyo color combine con
        "{$colorName}" ({$colorHex}). Elegí vos el objeto más icónico para ese
        color exacto.

        Fondo liso, en un tono de la misma familia de color, más oscuro o más
        claro que el objeto para que se distinga. Luz suave de estudio,
        centrado, cuadrado.

        Nunca: manos, personas, uñas, cabello, texto, logos, marcas de agua,
        más de un objeto.
        TXT;
    }
}
