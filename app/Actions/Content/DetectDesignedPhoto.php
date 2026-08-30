<?php

namespace App\Actions\Content;

use App\Models\ContentUpload;
use App\Support\MediaUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avisa cuando una foto "para editar" ya trae texto o diseño montado encima.
 *
 * Nace de un caso real (30-ago): Josean subió como material crudo fotos que
 * ya eran flyers terminados —título, recuadro de precio, dirección, todo ya
 * impreso— y el sistema, que no toca el contenido de una foto, le estampó
 * SU PROPIO titular y pie de contacto arriba. Resultado: texto sobre texto,
 * ilegible. El sistema no puede saberlo mirando el campo `purpose` —eso lo
 * elige ella al subir— así que se lo pregunta al modelo de visión.
 *
 * **Nunca bloquea.** Es un aviso, no una validación: si el modelo no
 * contesta o dice que no está seguro, se sigue igual que antes de que esto
 * existiera.
 *
 * **Barato a propósito.** Una sola foto, la primera de la tanda, y una
 * respuesta de una palabra: se llama una vez por tanda, no por foto, con el
 * mismo modelo económico que ya usa la sugerencia de notas.
 */
class DetectDesignedPhoto
{
    public function handle(ContentUpload $upload): bool
    {
        $url = MediaUrl::resolve($upload->path);

        if ($url === null) {
            return false;
        }

        $config = config('services.assistant');
        $key = $config['api_key'] ?? null;

        if (! is_string($key) || $key === '') {
            return false;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(30)
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    'model' => $config['vision_model'] ?? 'google/gemini-2.5-flash',
                    // Una palabra alcanza: SI o NO.
                    'max_completion_tokens' => 200,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $this->instructions()],
                            ['type' => 'image_url', 'image_url' => ['url' => $url]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException) {
            // Sin aviso se sigue igual que siempre: la foto se usa tal cual.
            return false;
        }

        if ($response->failed()) {
            Log::warning('No se pudo revisar si la foto ya estaba diseñada.', ['status' => $response->status()]);

            return false;
        }

        $texto = mb_strtoupper(trim((string) $response->json('choices.0.message.content', '')));

        return str_starts_with($texto, 'SI') || str_starts_with($texto, 'SÍ');
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Esta foto la subió una profesional de belleza como material CRUDO para
        armar un post: un sistema le va a imprimir ENCIMA su propio título y su
        propio pie de contacto.

        Fijate SOLO en esto: ¿la foto YA TRAE, puesto digitalmente, algún título
        grande, un recuadro de precio o de oferta, un pie con dirección o
        teléfono, o cualquier otro texto de diseño superpuesto? Eso no incluye
        una marca de agua chiquita ni el logo de una red social.

        Si la foto es solo la fotografía de su trabajo (un peinado, unas uñas,
        una clienta), sin ningún texto de diseño encima, contestá exactamente:
        NO

        Si la foto YA es un flyer o post terminado, con texto o cajas de diseño
        ya impresos, contestá exactamente:
        SI

        Una sola palabra, SI o NO, nada más.
        TXT;
    }
}
