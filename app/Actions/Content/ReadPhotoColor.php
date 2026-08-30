<?php

namespace App\Actions\Content;

use App\Models\ContentUpload;
use App\Support\MediaUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Propone de qué color es el trabajo de la foto, para que ella lo corrija.
 *
 * **Por qué no se calcula de los píxeles.** Se intentó, con tres fórmulas
 * distintas, y contra las fotos reales de Vane ninguna funcionó: en una foto
 * de uñas lo que más superficie ocupa es la piel, la ropa y la mesa, así que
 * unas uñas rosa daban "negro" porque ganaba la ropa del fondo. Las uñas son
 * una parte chica del cuadro y no hay fórmula que las encuentre sin saber
 * dónde están.
 *
 * **El modelo mira y dice; no dibuja.** Igual que ReadReferenceStyle: lo que
 * devuelve es un nombre y un tono que entran al motor de plantillas, nunca
 * una imagen.
 *
 * **Y lo que dice es una propuesta, no una sentencia.** Se le muestra a ella
 * ya escrito para que lo cambie con sus palabras — el mismo arreglo que ya
 * funciona con las notas de las referencias, y que ella misma pidió acá:
 * «algo así como que yo detecto tal cosa, ¿puedes decirnos algo más de los
 * colores de las uñas como tú lo ves, con tu propia palabra?».
 *
 * **Barato a propósito.** Una sola foto, la primera de la tanda, y una
 * respuesta de dos campos.
 */
class ReadPhotoColor
{
    /**
     * @return array{nombre: string, hex: string}|null
     */
    public function handle(ContentUpload $upload): ?array
    {
        $url = MediaUrl::resolve($upload->path);

        if ($url === null) {
            return null;
        }

        $config = config('services.assistant');
        $key = $config['api_key'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(45)
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    'model' => $config['vision_model'] ?? 'google/gemini-2.5-flash',
                    'max_completion_tokens' => 300,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $this->instructions()],
                            ['type' => 'image_url', 'image_url' => ['url' => $url]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException) {
            // Sin propuesta se sigue igual: ella lo escribe a mano, o lo deja.
            return null;
        }

        if ($response->failed()) {
            Log::warning('No se pudo leer el color del trabajo.', ['status' => $response->status()]);

            return null;
        }

        return $this->parse((string) $response->json('choices.0.message.content', ''));
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Mirá esta foto: es el trabajo de una profesional de belleza — uñas, cabello,
        pestañas, lo que sea.

        Decime SOLO de qué color es EL TRABAJO. No el color de la piel, ni de la ropa,
        ni del fondo, ni de la mesa. Si son uñas, el color del esmalte. Si es cabello,
        el color del tinte.

        Si tiene dos tonos (un degradado, una francesa), nombralos juntos.

        Respondé SOLO con este JSON, sin explicar nada y sin ```:

        {
          "nombre": "rosa bebé con blanco",
          "hex": "#F2D5D5"
        }

        nombre: en español, corto y natural, como se lo diría una manicurista a su
        clienta. Máximo cuatro palabras. En minúscula.
        hex: el tono principal del trabajo, el que se usaría para pintar un fondo que
        combine con la foto.
        TXT;
    }

    /**
     * @return array{nombre: string, hex: string}|null
     */
    private function parse(string $answer): ?array
    {
        // Los modelos envuelven el JSON en ``` casi siempre, aunque se les
        // pida que no.
        if (preg_match('/\{.*\}/su', $answer, $match) !== 1) {
            return null;
        }

        $decoded = json_decode($match[0], true);

        if (! is_array($decoded)) {
            return null;
        }

        $nombre = trim((string) ($decoded['nombre'] ?? ''));
        $hex = strtoupper(trim((string) ($decoded['hex'] ?? '')));

        // Sin un tono válido no sirve para pintar nada, y un nombre suelto
        // sin color deja las plantillas a medias.
        if ($nombre === '' || preg_match('/^#[0-9A-F]{6}$/', $hex) !== 1) {
            return null;
        }

        return ['nombre' => mb_substr($nombre, 0, 60), 'hex' => $hex];
    }
}
