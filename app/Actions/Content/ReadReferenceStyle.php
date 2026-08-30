<?php

namespace App\Actions\Content;

use App\Enums\ContentPurpose;
use App\Enums\UploadKind;
use App\Models\ContentUpload;
use App\Models\Provider;
use App\Support\MediaUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lee las referencias que ella guardó y saca de ahí una ficha de estilo.
 *
 * **El modelo mira y describe; no dibuja nada.** Es la diferencia entre
 * pedirle "hazme un post igual a este" —que sale distinto cada vez— y
 * pedirle "decime qué colores y qué letra usa este"— que lo acierta. Lo que
 * devuelve son ajustes que entran al motor de plantillas, no una imagen.
 *
 * Así se conectan por fin las referencias con el DISEÑO. Hasta ahora solo
 * alimentaban el texto, y ella lo notó: «siento que no usa las cosas que
 * están como referencia».
 *
 * Se ejecuta cuando ella lo pide, no en cada post: es una llamada con
 * imágenes y no tiene sentido repetirla si las referencias no cambiaron.
 */
class ReadReferenceStyle
{
    /**
     * Cuántas referencias se le muestran. Más no mejora la lectura y encarece
     * la llamada; las más nuevas son las que representan lo que le gusta hoy.
     */
    private const MAX_REFERENCES = 6;

    /**
     * Los videos salen aparte, con el mismo tope que las fotos.
     *
     * Antes era más chico (2) para ahorrar, pero en una cuenta real (Josean,
     * 30-ago) eso tomaba solo los DOS MÁS RECIENTES de sus seis videos —y
     * esos dos resultaron ser clips de "hooks" de marketing sin relación con
     * el diseño, mientras que los dos que sí mostraban la tendencia que ella
     * quería (letra script apilada con la gruesa) eran justo los más viejos
     * y quedaban afuera. Con el mismo tope que las fotos, nada se descarta
     * en silencio por orden de subida.
     */
    private const MAX_VIDEO_REFERENCES = self::MAX_REFERENCES;

    public function __construct(
        private readonly ExtractVideoFrame $extractFrame,
    ) {}

    /**
     * @return array{ok: true, style: array<string, mixed>}|array{ok: false, error: string}
     */
    public function handle(Provider $provider): array
    {
        $imageUrls = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->references()
            ->where('kind', UploadKind::Image->value)
            ->latest()
            ->limit(self::MAX_REFERENCES)
            ->pluck('path')
            ->map(fn (string $path): string => MediaUrl::resolve($path))
            ->all();

        // Un video no se le puede pasar tal cual al modelo de texto/visión de
        // esta app: se le saca un fotograma cerca del arranque y se lee como
        // a cualquier otra imagen. Ella lo notó primero: «siento que no está
        // leyendo esa parte del video, [...] no agrega ese tipo de letra».
        $videoFrames = ContentUpload::query()
            ->where('provider_id', $provider->id)
            ->references()
            ->where('kind', UploadKind::Video->value)
            ->latest()
            ->limit(self::MAX_VIDEO_REFERENCES)
            ->pluck('path')
            ->map(function (string $path): ?string {
                try {
                    $binary = Storage::disk('r2')->get($path);
                } catch (Throwable) {
                    return null;
                }

                if ($binary === null) {
                    return null;
                }

                $frame = $this->extractFrame->handle($binary);

                return $frame === null ? null : 'data:image/jpeg;base64,'.base64_encode($frame);
            })
            ->filter()
            ->values()
            ->all();

        $urls = [...$imageUrls, ...$videoFrames];

        if ($urls === []) {
            return ['ok' => false, 'error' => 'admin.contentStyleNoReferences'];
        }

        $config = config('services.assistant');
        $key = $config['api_key'] ?? null;

        if (! is_string($key) || $key === '') {
            return ['ok' => false, 'error' => 'admin.contentStyleUnavailable'];
        }

        $content = [['type' => 'text', 'text' => $this->instructions()]];

        foreach ($urls as $url) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
        }

        try {
            $response = Http::withToken($key)
                ->timeout(90)
                ->post(rtrim((string) $config['base_url'], '/').'/chat/completions', [
                    // Un modelo que ve imágenes, que no es el mismo que escribe
                    // los textos. Comprobado contra sus referencias reales: sacó
                    // el rosa exacto y acertó la tipografía donde otro falló.
                    'model' => $config['vision_model'] ?? 'google/gemini-2.5-flash',
                    'max_completion_tokens' => 800,
                    'messages' => [['role' => 'user', 'content' => $content]],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('El modelo de visión no respondió al leer las referencias.', [
                'provider' => $provider->slug,
                'reason' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'admin.contentStyleUnavailable'];
        }

        if ($response->failed()) {
            Log::warning('El modelo de visión rechazó la lectura de referencias.', [
                'provider' => $provider->slug,
                'status' => $response->status(),
            ]);

            return ['ok' => false, 'error' => 'admin.contentStyleUnavailable'];
        }

        $style = $this->parse((string) $response->json('choices.0.message.content', ''));

        if ($style === null) {
            Log::warning('La ficha de estilo vino en un formato que no se pudo leer.', [
                'provider' => $provider->slug,
            ]);

            return ['ok' => false, 'error' => 'admin.contentStyleUnreadable'];
        }

        $style['referencias'] = count($urls);

        return ['ok' => true, 'style' => $style];
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Mirá estas imágenes: son publicaciones de Instagram que a una profesional de
        belleza le gustan y quiere imitar en las suyas.

        NO describas lo que aparece en las fotos. Fijate SOLO en cómo están diseñadas:
        el color del texto y de los recuadros, dónde cae el texto, qué tipo de letra
        usan, si hay una capa oscura sobre la foto.

        Si las imágenes no coinciden entre sí, describí lo que más se repite.

        Respondé SOLO con este JSON, sin explicar nada y sin ```:

        {
          "colores": ["#RRGGBB", "#RRGGBB", "#RRGGBB"],
          "posicion_texto": "arriba" | "centro" | "abajo",
          "tipografia": "serif" | "condensada",
          "estilo_titular": "bloques" | "franja" | "limpio" | "cursiva",
          "lleva_precio": true | false
        }

        colores: exactamente tres, del texto y sus fondos, NO de las fotos. El primero
        el más oscuro o dominante, el segundo el de acento (el que resalta), el tercero
        otro de apoyo.
        estilo_titular: "bloques" si cada línea tiene su propio recuadro de color,
        "franja" si hay una banda de lado a lado, "limpio" si el texto va suelto sobre
        la foto sin fondo, "cursiva" si hay dos palabras apiladas y UNA de ellas está en
        letra script/manuscrita (inclinada, con trazos que se conectan) mientras la otra
        va en mayúsculas gruesas de molde — no importa cuál de las dos va arriba.
        TXT;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parse(string $answer): ?array
    {
        // Los modelos devuelven el JSON envuelto en ``` casi siempre, aunque se
        // les pida que no.
        if (preg_match('/\{.*\}/su', $answer, $match) !== 1) {
            return null;
        }

        $decoded = json_decode($match[0], true);

        if (! is_array($decoded)) {
            return null;
        }

        $colores = array_values(array_filter(
            (array) ($decoded['colores'] ?? []),
            static fn (mixed $c): bool => is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1,
        ));

        // Sin al menos dos colores válidos la ficha no aporta nada sobre lo que
        // ya dicta el rubro, y es mejor decirlo que guardar algo inservible.
        if (count($colores) < 2) {
            return null;
        }

        return [
            'colores' => array_slice($colores, 0, 3),
            'posicion_texto' => $this->oneOf($decoded['posicion_texto'] ?? null, ['arriba', 'centro', 'abajo']),
            'tipografia' => $this->oneOf($decoded['tipografia'] ?? null, ['serif', 'condensada']),
            'estilo_titular' => $this->oneOf($decoded['estilo_titular'] ?? null, ['bloques', 'franja', 'limpio', 'cursiva']),
            'lleva_precio' => (bool) ($decoded['lleva_precio'] ?? false),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }
}
