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
     * @return array{caption: string, hashtags: list<string>}
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
                    'max_completion_tokens' => 400,
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
        Escribes descripciones de Instagram para profesionales de belleza en Estados Unidos.

        Reglas:
        - Español natural y cercano, como habla una manicurista o peluquera con sus clientas.
        - Entre 1 y 3 frases. Nada de párrafos largos.
        - Nunca inventes precios, promociones, ni cuánto dura un servicio.
        - Nunca digas que el trabajo lo hizo una IA.
        - Cierra invitando a agendar, sin sonar a anuncio de televisión.

        Responde EXACTAMENTE en este formato, sin nada más:
        DESCRIPCION: <el texto>
        HASHTAGS: <5 a 8 hashtags separados por espacios, cada uno empezando por #>
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

        $notes = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->references()
            ->whereNotNull('note')
            ->latest()
            ->limit(5)
            ->pluck('note')
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
     * @return array{caption: string, hashtags: list<string>}
     */
    private function parse(string $answer, Provider $provider): array
    {
        $caption = '';
        $hashtags = [];

        if (preg_match('/DESCRIPCION:\s*(.+?)(?=\n\s*HASHTAGS:|$)/su', $answer, $match) === 1) {
            $caption = trim($match[1]);
        }

        if (preg_match('/HASHTAGS:\s*(.+)/su', $answer, $match) === 1) {
            preg_match_all('/#[\p{L}\p{N}_]+/u', $match[1], $found);
            $hashtags = array_values(array_unique($found[0]));
        }

        // Un formato inesperado no puede dejarla sin texto.
        if ($caption === '') {
            return $this->fallback($provider);
        }

        return ['caption' => $caption, 'hashtags' => array_slice($hashtags, 0, 8)];
    }

    /**
     * @return array{caption: string, hashtags: list<string>}
     */
    private function fallback(Provider $provider): array
    {
        return [
            'caption' => trim(($provider->public_name ?? '').' — nuevo trabajo. Escribí acá tu descripción y agendá por el enlace de mi perfil.'),
            'hashtags' => [],
        ];
    }
}
