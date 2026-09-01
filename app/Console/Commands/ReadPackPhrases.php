<?php

namespace App\Console\Commands;

use App\Models\ContentAsset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;

/**
 * Lee qué dice cada frase del pack y lo guarda en su fila.
 *
 * **Para qué sirve saberlo.** Sin el texto, estampar una frase es una
 * ruleta: sale "Rubber" sobre un trabajo de acrílicas y el post miente. Con
 * el texto, la app puede estampar la palabra del servicio que de verdad se
 * hizo — que es lo que vuelve al pack algo más que decoración.
 *
 * **Corre acá y no en una máquina de escritorio a propósito.** La credencial
 * del modelo vive en el entorno de producción; mover el trabajo hasta la
 * credencial es más seguro que mover la credencial hasta el trabajo. Además
 * las piezas ya están en R2 y catalogadas, así que no hace falta ningún
 * archivo intermedio.
 *
 * Es resumible: solo mira las que todavía no tienen texto, así una caída a
 * mitad de camino no obliga a pagar de nuevo lo ya leído.
 */
#[Signature('content:leer-frases {--limite=0 : Cuántas leer como mucho, 0 = todas} {--rehacer : Volver a leer también las que ya tienen texto}')]
#[Description('Lee con el modelo de visión qué dice cada frase del pack.')]
class ReadPackPhrases extends Command
{
    public function handle(): int
    {
        $config = config('services.assistant');
        $key = $config['api_key'] ?? null;

        if (! is_string($key) || $key === '' || str_contains($key, 'fake')) {
            $this->error('No hay credencial del modelo en este entorno.');

            return self::FAILURE;
        }

        $query = ContentAsset::query()->where('kind', 'frase');

        if (! $this->option('rehacer')) {
            $query->whereNull('text');
        }

        $limite = (int) $this->option('limite');

        if ($limite > 0) {
            $query->limit($limite);
        }

        $frases = $query->get();

        if ($frases->isEmpty()) {
            $this->info('No queda ninguna frase por leer.');

            return self::SUCCESS;
        }

        $this->info("Leyendo {$frases->count()} frases...");

        $leidas = 0;
        $fallidas = 0;

        foreach ($frases as $frase) {
            $texto = $this->read($frase, (string) $key, (string) $config['base_url'], (string) ($config['vision_model'] ?? 'google/gemini-2.5-flash'));

            if ($texto === null) {
                $fallidas++;

                continue;
            }

            $frase->update([
                'text' => $texto,
                // El slug es con lo que después se busca el servicio: sin
                // acentos ni mayúsculas, "Acrílicas" y "acrilicas" tienen
                // que encontrarse.
                'slug' => Str::slug($texto),
            ]);

            $leidas++;

            if ($leidas % 25 === 0) {
                $this->line("  ... {$leidas}");
            }
        }

        $this->newLine();
        $this->info("Leídas: {$leidas}. Fallidas: {$fallidas}.");

        return self::SUCCESS;
    }

    private function read(ContentAsset $frase, string $key, string $baseUrl, string $model): ?string
    {
        $imagen = $this->flatten($frase->path);

        if ($imagen === null) {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->timeout(45)
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => $model,
                    'max_completion_tokens' => 120,
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $this->instructions()],
                            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,'.base64_encode($imagen)]],
                        ],
                    ]],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $texto = trim((string) $response->json('choices.0.message.content', ''));

        if ($texto === '' || str_contains(Str::upper($texto), 'SIN_TEXTO')) {
            return null;
        }

        // Una respuesta larga es el modelo explicando en vez de transcribir.
        return mb_strlen($texto) > 120 ? null : $texto;
    }

    /**
     * La pieza aplanada sobre gris medio, chica.
     *
     * Sobre gris y no sobre blanco ni negro porque el pack trae la misma
     * frase en tinta clara y oscura: sobre blanco desaparecen las claras y
     * sobre negro las oscuras, y se perdería la mitad del catálogo.
     */
    private function flatten(string $path): ?string
    {
        try {
            $bytes = Storage::disk('r2')->get($path);
        } catch (\Throwable) {
            return null;
        }

        if ($bytes === null || $bytes === '') {
            return null;
        }

        try {
            $arte = new Imagick;
            $arte->readImageBlob($bytes);

            $fondo = new Imagick;
            $fondo->newImage($arte->getImageWidth(), $arte->getImageHeight(), new ImagickPixel('#9aa0a6'));
            $fondo->compositeImage($arte, Imagick::COMPOSITE_OVER, 0, 0);
            $fondo->setImageFormat('jpeg');
            $fondo->thumbnailImage(512, 512, true);
            $fondo->setImageCompressionQuality(80);

            $salida = (string) $fondo;

            $arte->destroy();
            $fondo->destroy();

            return $salida;
        } catch (\Throwable) {
            return null;
        }
    }

    private function instructions(): string
    {
        return <<<'TXT'
        Esta imagen contiene SOLO texto decorativo, sin fotos.

        Transcribí exactamente el texto que ves, respetando tildes y el orden en
        que aparece. Si hay dos renglones, separalos con " / ".

        No expliques nada. No agregues comillas. Respondé solo con el texto.
        Si no hay texto legible, respondé exactamente: SIN_TEXTO
        TXT;
    }
}
