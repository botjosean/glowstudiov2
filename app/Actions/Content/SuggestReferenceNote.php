<?php

namespace App\Actions\Content;

use App\Models\ContentUpload;
use App\Support\MediaUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Propone, en las palabras de ella, qué tiene de bueno la referencia que
 * acaba de guardar.
 *
 * Nace de una observación suya que vale para toda la app: «a la gente le da
 * flojera pensar». Un cuadro de texto vacío después de subir cinco fotos se
 * queda vacío, y una referencia sin nota enseña la mitad — es la nota, no la
 * imagen, lo que le dice al sistema QUÉ mirar.
 *
 * Es una sugerencia, no una decisión: sale como texto que ella puede borrar,
 * cambiar o aceptar tal cual.
 *
 * **Barato a propósito.** Dos imágenes como mucho y respuesta corta: se llama
 * una vez por tanda, no por foto, y con el modelo de visión más económico.
 */
class SuggestReferenceNote
{
    /** Con dos alcanza para describir un estilo; más solo encarece. */
    private const MAX_IMAGES = 2;

    /**
     * @param  Collection<int, ContentUpload>  $uploads
     */
    public function handle(Collection $uploads): ?string
    {
        $urls = $uploads
            ->take(self::MAX_IMAGES)
            ->map(fn (ContentUpload $u): string => MediaUrl::resolve($u->path))
            ->all();

        if ($urls === []) {
            return null;
        }

        $config = config('services.assistant');
        $key = $config['api_key'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        $content = [['type' => 'text', 'text' => $this->instructions()]];

        foreach ($urls as $url) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        }

        try {
            $response = Http::withToken($key)
                ->timeout(45)
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    'model' => $config['vision_model'] ?? 'google/gemini-2.5-flash',
                    // Corto: es una nota de dos frases, no un ensayo, y cada
                    // token de más se paga en cada tanda que ella sube.
                    'max_completion_tokens' => 400,
                    'messages' => [['role' => 'user', 'content' => $content]],
                ]);
        } catch (ConnectionException) {
            // Sin sugerencia se sigue igual: ella escribe la nota a mano, que
            // es exactamente como funcionaba antes.
            return null;
        }

        if ($response->failed()) {
            Log::warning('No se pudo sugerir la nota de la referencia.', ['status' => $response->status()]);

            return null;
        }

        $texto = trim((string) $response->json('choices.0.message.content', ''));

        // Los modelos suelen envolver en comillas o anteponer "Sugerencia:".
        $texto = trim(preg_replace('/^(sugerencia|nota)\s*:\s*/iu', '', $texto) ?? $texto);
        $texto = trim($texto, "\"'“”");

        return $texto === '' ? null : mb_substr($texto, 0, 400);
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Estas imágenes son publicaciones de Instagram que a una profesional de belleza
        le gustaron y guardó como referencia para sus propios posts.

        Escribí, EN PRIMERA PERSONA y como si lo dijera ella, qué le puede gustar del
        DISEÑO: los colores, dónde va el texto, el tipo de letra, el fondo, si se ve
        limpio o cargado.

        No describas a la persona ni el servicio de la foto. Fijate en cómo está
        armada la publicación, no en qué muestra.

        Dos frases como mucho, en español natural y sencillo. Sin comillas, sin
        títulos, sin viñetas. Ejemplo del tono:
        "Me gusta el fondo oscuro con las letras doradas y que el texto vaya abajo.
        Se ve elegante y se lee de una."
        TXT;
    }
}
