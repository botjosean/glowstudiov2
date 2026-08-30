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

        // Un reintento y no más: es lo que hace falta para un vacío
        // ocasional, y una descripción no vale una cadena de reintentos que
        // demoren el post.
        for ($intento = 1; $intento <= 2; $intento++) {
            $texto = $this->ask($provider, $config, $apiKey);

            if ($texto === null) {
                // Sin respuesta o rechazada: reintentar no cambia nada, ya
                // quedó registrado en ask().
                return $this->fallback($provider);
            }

            if (trim($texto) !== '') {
                return $this->parse($texto, $provider);
            }

            Log::warning('El modelo devolvió una respuesta vacía al escribir la descripción.', [
                'provider' => $provider->slug,
                'intento' => $intento,
            ]);
        }

        return $this->fallback($provider);
    }

    /**
     * Una llamada al modelo. Devuelve el texto crudo, o null si no hubo
     * respuesta que valga la pena reintentar (sin conexión o rechazada).
     *
     * @param  array<string, mixed>  $config
     */
    private function ask(Provider $provider, array $config, string $apiKey): ?string
    {
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
                    // Un titular de tres líneas no necesita razonar en
                    // profundidad, y dejarlo sin tope es justo lo que vació
                    // la respuesta en un post real (30-ago): el modelo gastó
                    // todo el presupuesto pensando y no dejó nada para
                    // contestar. 'low' salvo que se configure otra cosa.
                    'reasoning' => ['effort' => $config['reasoning_effort'] ?: 'low'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instructions($this->wantsPrice($provider))],
                        ['role' => 'user', 'content' => $this->brief($provider)],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('El modelo no respondió al escribir la descripción.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('El modelo rechazó la petición de la descripción.', [
                'provider' => $provider->slug,
                'status' => $response->status(),
            ]);

            return null;
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    /**
     * ¿Este post lleva precio en el titular?
     *
     * Lo dice la ficha leída de sus referencias: si el diseño que ella eligió
     * como plantilla no muestra precios, el suyo tampoco. Sin ficha se
     * sortea, para que no salgan todos iguales.
     */
    private function wantsPrice(Provider $provider): bool
    {
        $ficha = $provider->content_style;

        if (is_array($ficha) && array_key_exists('lleva_precio', $ficha)) {
            return (bool) $ficha['lleva_precio'];
        }

        return random_int(0, 1) === 1;
    }

    /**
     * Qué se le pide al modelo.
     *
     * El precio no va siempre. Antes se le ordenaba usar SIEMPRE un servicio
     * con su precio, y todos los posts salían con el mismo "ACRILICAS DESDE
     * 65" encima — ella lo señaló directo: «siempre pone el precio desde 65».
     * Ahora manda lo que dicen sus propias referencias (`lleva_precio` de la
     * ficha, que se leía pero no se usaba en ningún lado), y sin ficha se
     * sortea, para que no sean todos iguales.
     */
    private function instructions(bool $conPrecio): string
    {
        $reglaPrecio = $conPrecio
            ? <<<'PRECIO'
               USA UN SERVICIO Y SU PRECIO DE VERDAD de la lista de abajo: el nombre
               del servicio en una línea y el precio en otra, como
               "ACRILICAS / DESDE 65" o "BALAYAGE / 200".
               Nunca inventes un precio ni cambies el de la lista. Si no hay lista de
               servicios, usá 2 o 3 palabras con gancho y ningún número.
            PRECIO
            : <<<'PRECIO'
               NO PONGAS NINGÚN PRECIO ni número de dinero en el titular, aunque
               tengas la lista de servicios abajo. Usá 2 o 3 palabras con gancho:
               el nombre del servicio ("ACRILICAS / NUEVO SET"), una invitación
               ("CITAS ABIERTAS / ESTA SEMANA") o el resultado ("TRANSFORMACIÓN /
               REAL"). Variá: no uses siempre la misma fórmula.
            PRECIO;

        return <<<TXT
        Escribes posts de Instagram para profesionales de belleza en Estados Unidos.

        Devuelves tres cosas:

        1. TITULAR: 2 o 3 líneas cortas que van impresas GRANDES sobre la foto. Es lo
           que hace que alguien pare de deslizar.

        {$reglaPrecio}

           Nunca asumas uñas, cabello ni ningún servicio puntual si el rubro no
           viene indicado más abajo.

           SOLO letras, números y espacios. Ni un emoji, ni un símbolo de dólar, ni un
           asterisco: la tipografía del cartel no los dibuja y dejan un hueco de color
           vacío. Escribí el precio en números pelados: 65, no \$65.
        2. DESCRIPCION: 1 a 3 frases, español natural y cercano, como habla una
           manicurista o peluquera con sus clientas.
        3. HASHTAGS: entre 5 y 8, cada uno empezando por #. Tienen que ser del rubro
           de ella si te lo doy más abajo (uñas, cabello, cejas, barbería, lo que
           sea) — nunca de un rubro distinto. Si no te doy el rubro, usá solo
           hashtags genéricos de belleza/negocio local (#beauty #localbusiness), sin
           inventar uno de uñas ni de ningún servicio puntual.

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

        // Sus servicios con precio de verdad. Es la diferencia entre un
        // titular que dice "NUEVOS" y uno que dice "ACRÍLICAS $65", que es
        // justo lo que separa sus referencias de lo que salía antes.
        $services = $provider->services()
            ->where('is_active', true)
            ->orderBy('position')
            ->limit(10)
            ->get(['name', 'price']);

        if ($services->isNotEmpty()) {
            $lines[] = 'Sus servicios y precios reales:';

            foreach ($services as $service) {
                $lines[] = "- {$service->name}: \${$service->price}";
            }
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

        // Con tilde o sin ella: el formato pedido va sin acento pero el
        // modelo, escribiendo español de verdad, a veces contesta
        // "DESCRIPCIÓN" — y sin este acento suelto la etiqueta se colaba
        // dentro del texto del post, visto en una generación real de Josean.
        if (preg_match('/DESCRIPCI[OÓ]N:\s*(.+?)(?=\n\s*HASHTAGS:|$)/su', $answer, $match) === 1) {
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
