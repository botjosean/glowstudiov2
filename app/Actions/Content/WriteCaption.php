<?php

namespace App\Actions\Content;

use App\Models\ContentUpload;
use App\Models\Provider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Escribe la descripción y los hashtags del post.
 *
 * Usa el mismo modelo y la misma clave que ya tiene configurado el asistente
 * de WhatsApp (`services.assistant`), así que no hace falta ninguna cuenta ni
 * clave nueva.
 *
 * **Nunca hace fallar el post.** Si el modelo no contesta o no está
 * configurado, se devuelve un texto de arranque y ella lo edita. Quedarse sin
 * collage porque falló el texto sería el peor intercambio posible.
 */
class WriteCaption
{
    /**
     * @return array{caption: string, hashtags: list<string>, headline: list<string>}
     */
    public function handle(Provider $provider): array
    {
        $config = config('services.assistant');
        $apiKey = $config['api_key'] ?? null;

        if (! is_string($apiKey) || $apiKey === '') {
            return $this->fallback($provider);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) ($config['timeout'] ?? 30))
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    'model' => $config['model'],
                    'temperature' => 0.8,
                    // Holgado a propósito: el modelo configurado razona antes
                    // de contestar, y ese razonamiento se come el mismo
                    // presupuesto. Con 400 la respuesta salía cortada a media
                    // frase y sin hashtags — visto en producción el 29-ago.
                    'max_completion_tokens' => 1500,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => $this->brief($provider)],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('El modelo no respondió al escribir la descripción.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return $this->fallback($provider);
        }

        if ($response->failed()) {
            Log::warning('El modelo rechazó la petición de la descripción.', [
                'provider' => $provider->slug,
                'status' => $response->status(),
            ]);

            return $this->fallback($provider);
        }

        return $this->parse((string) $response->json('choices.0.message.content', ''), $provider);
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Escribes posts de Instagram para profesionales de belleza en Estados Unidos.

        Devuelves tres cosas:

        1. TITULAR: 2 o 3 palabras, UNA POR LÍNEA, que van impresas GRANDES sobre la
           foto. Es lo que hace que alguien pare de deslizar. Cortas y con gancho, como
           "TRENDING / NAILS / VERANO" o "CITAS / ABIERTAS / YA".
           SOLO letras y espacios. Ni un emoji, ni un asterisco, ni un guion suelto:
           la tipografía del cartel no los dibuja y dejan un hueco de color vacío.
           Si no se te ocurre una tercera palabra, manda dos y ya.
        2. DESCRIPCION: 1 a 3 frases, español natural y cercano, como habla una
           manicurista o peluquera con sus clientas.
        3. HASHTAGS: entre 5 y 8, cada uno empezando por #.

        Nunca inventes precios ni cuánto dura un servicio. Nunca digas que lo hizo una IA.

        Responde EXACTAMENTE en este formato y nada más:
        TITULAR: palabra1 | palabra2 | palabra3
        DESCRIPCION: <el texto>
        HASHTAGS: #uno #dos #tres
        TXT;
    }

    /**
     * Lo que el modelo sabe de ella. Las notas de sus referencias son la
     * parte que de verdad cambia el resultado: son sus propias palabras
     * sobre qué le gusta.
     */
    private function brief(Provider $provider): string
    {
        $lines = ['Profesional: '.($provider->public_name ?? '')];

        if ($provider->business_category !== null) {
            $lines[] = 'Rubro: '.$provider->business_category->value;

            $subcategories = $provider->business_subcategories ?? [];

            if ($subcategories !== []) {
                $lines[] = 'Especialidades: '.implode(', ', $subcategories);
            }
        }

        if (($provider->bio ?? '') !== '') {
            $lines[] = 'Cómo se describe: '.$provider->bio;
        }

        // unique() y no solo limit(): antes la nota se copiaba en cada foto de
        // la tanda, así que una sola subida de diez fotos llenaba las cinco
        // ranuras con el mismo texto y tapaba todas las demás referencias.
        // Ya no se guarda repetida, pero las que se subieron antes sí lo están.
        $notes = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->references()
            ->whereNotNull('note')
            ->latest()
            ->pluck('note')
            ->map(fn (string $note): string => trim($note))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();

        if ($notes !== []) {
            $lines[] = 'Lo que ella misma dijo que le gusta de su contenido:';

            foreach ($notes as $note) {
                $lines[] = '- '.$note;
            }
        }

        $lines[] = 'Escribe la descripción para un collage con cuatro fotos de su trabajo de hoy.';

        return implode("\n", $lines);
    }

    /**
     * @return array{caption: string, hashtags: list<string>, headline: list<string>}
     */
    private function parse(string $answer, Provider $provider): array
    {
        $headline = [];
        $caption = '';
        $hashtags = [];

        if (preg_match('/TITULAR:\s*(.+)/u', $answer, $match) === 1) {
            $headline = array_values(array_filter(array_map(
                static fn (string $word): string => trim($word),
                preg_split('/[|\n\/]+/u', $match[1]) ?: [],
            )));
            $headline = array_slice($headline, 0, 3);
        }

        if (preg_match('/DESCRIPCION:\s*(.+?)(?=\n\s*HASHTAGS:|$)/su', $answer, $match) === 1) {
            $caption = trim($match[1]);
        }

        if (preg_match('/HASHTAGS:\s*(.+)/su', $answer, $match) === 1) {
            preg_match_all('/#[\p{L}\p{N}_]+/u', $match[1], $found);
            $hashtags = array_values(array_unique($found[0]));
        }

        if ($caption === '') {
            // Sin el marcador esperado, pero con texto: vale más su prosa que
            // el texto de relleno. Antes esto caía al respaldo en silencio y
            // parecía que el modelo no se estaba llamando siquiera.
            $loose = trim(preg_replace('/^(TITULAR|HASHTAGS):.*$/mu', '', $answer) ?? '');

            if ($loose !== '') {
                $caption = $loose;
            } else {
                Log::warning('El modelo contestó en un formato que no se pudo leer; se usa el texto de respaldo.', [
                    'provider' => $provider->slug,
                    'muestra' => mb_substr($answer, 0, 200),
                ]);

                return $this->fallback($provider);
            }
        }

        return [
            'caption' => $caption,
            'hashtags' => array_slice($hashtags, 0, 8),
            'headline' => $headline,
        ];
    }

    /**
     * @return array{caption: string, hashtags: list<string>, headline: list<string>}
     */
    private function fallback(Provider $provider): array
    {
        return [
            'caption' => trim(($provider->public_name ?? '').' — nuevo trabajo. Escribí acá tu descripción y agendá por el enlace de mi perfil.'),
            'hashtags' => [],
            // Sin titular inventado: un bloque de texto grande con una frase
            // de relleno encima de su trabajo es peor que ninguno.
            'headline' => [],
        ];
    }
}
