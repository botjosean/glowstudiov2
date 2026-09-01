<?php

namespace App\Console\Commands;

use App\Models\ContentAsset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;

/**
 * Mete el pack de contenido que ella compró en el catálogo de la app.
 *
 * El pack viene en carpetas por rubro (MANICURISTA, LASHISTA, ESTILISTA,
 * GENERALES) y dentro por tipo (FRASES, ELEMENTOS, MARCOS, SOMBRAS Y
 * DEGRADADOS, ELEMENTOS DECORATIVOS). Todas las piezas son PNG de 1080x1080
 * con transparencia — justo el lienzo que ya usan las plantillas, así que
 * entran como capas sin redimensionar nada.
 *
 * De cada una se guardan dos cosas que no se ven en el nombre del archivo y
 * que hacen falta para colocarla bien:
 *
 * - **La tinta.** El pack trae la misma frase en oscuro y en claro. Sin
 *   saber cuál es cuál no se puede elegir según la foto, y un titular claro
 *   sobre unas uñas blancas simplemente no se lee.
 * - **El encuadre.** El dibujo viene centrado en el lienzo; sin sus medidas
 *   reales no se puede recolocar arriba o abajo sin recortar a ciegas.
 *
 * Se corre una sola vez por pack. Repetirlo no duplica nada: la ruta es
 * única y se actualiza en su lugar.
 */
#[Signature('content:importar-pack {carpeta : La carpeta del pack ya descomprimido} {--seco : Solo mostrar lo que haría, sin subir nada}')]
#[Description('Importa el pack de contenido (frases, elementos, marcos, sombras) al catálogo.')]
class ImportContentPack extends Command
{
    /** Cómo se llama cada carpeta del pack acá adentro. */
    private const KINDS = [
        'FRASES' => 'frase',
        'ELEMENTOS' => 'elemento',
        'MARCOS' => 'marco',
        'SOMBRAS Y DEGRADADOS' => 'sombra',
        'ELEMENTOS DECORATIVOS' => 'decorativo',
    ];

    /** Y a qué rubro de la app corresponde cada carpeta de arriba. */
    private const TRADES = [
        'MANICURISTA' => 'nails',
        'ESTILISTA' => 'hair',
        'LASHISTA' => 'lashes_brows',
        'GENERALES' => null,
    ];

    public function handle(): int
    {
        $raiz = rtrim((string) $this->argument('carpeta'), '/');

        if (! is_dir($raiz)) {
            $this->error("No existe la carpeta: {$raiz}");

            return self::FAILURE;
        }

        $seco = (bool) $this->option('seco');
        $vistos = 0;
        $subidos = 0;
        $porGrupo = [];

        foreach ($this->archivos($raiz) as $archivo) {
            $clasificado = $this->clasificar($archivo, $raiz);

            if ($clasificado === null) {
                continue;
            }

            [$kind, $trade] = $clasificado;
            $vistos++;
            $clave = $kind.'/'.($trade ?? 'general');
            $porGrupo[$clave] = ($porGrupo[$clave] ?? 0) + 1;

            if ($seco) {
                continue;
            }

            $medida = $this->medir($archivo);

            if ($medida === null) {
                $this->warn('  ✗ no se pudo leer: '.basename($archivo));

                continue;
            }

            // La ruta se arma del contenido y no del nombre original: los
            // archivos se llaman "1.png", "10.png" en TODAS las carpetas, y
            // con eso se pisarían entre sí.
            $path = sprintf('pack/%s/%s/%s.png', $kind, $trade ?? 'general', substr(hash_file('sha256', $archivo), 0, 24));

            // La ruta sale del contenido, así que si ya está es idéntica: se
            // salta la subida. Eso vuelve barato volver a correr esto para
            // reclasificar, que es justo lo que hizo falta cuando un
            // degradado verde se estaba usando de sombra.
            if (! Storage::disk('r2')->exists($path)) {
                Storage::disk('r2')->put($path, (string) file_get_contents($archivo), [
                    'ContentType' => 'image/png',
                    'CacheControl' => 'public, max-age=31536000, immutable',
                ]);
            }

            ContentAsset::query()->updateOrCreate(['path' => $path], [
                'kind' => $kind,
                'trade' => $trade,
                'ink' => $medida['ink'],
                'box_x' => $medida['x'],
                'box_y' => $medida['y'],
                'box_w' => $medida['w'],
                'box_h' => $medida['h'],
            ]);

            $subidos++;

            if ($subidos % 50 === 0) {
                $this->line("  ... {$subidos}");
            }
        }

        $this->newLine();

        foreach ($porGrupo as $grupo => $cuantos) {
            $this->line(str_pad($grupo, 26).$cuantos);
        }

        $this->newLine();
        $this->info($seco ? "Se importarían {$vistos} piezas." : "Importadas {$subidos} de {$vistos}.");

        return self::SUCCESS;
    }

    /**
     * @return iterable<string>
     */
    private function archivos(string $raiz): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($it as $f) {
            if (strtolower($f->getExtension()) === 'png') {
                yield $f->getPathname();
            }
        }
    }

    /**
     * De qué tipo y rubro es, mirando la ruta.
     *
     * Se compara sin acentos ni emojis: las carpetas del pack vienen con
     * emoji en el nombre ("MANICURISTA💅🏻") y eso no sobrevive a una
     * comparación literal.
     *
     * @return array{0: string, 1: string|null}|null
     */
    private function clasificar(string $archivo, string $raiz): ?array
    {
        $rel = Str::upper(Str::ascii(str_replace($raiz, '', $archivo)));

        $kind = null;

        // El más específico primero: "ELEMENTOS DECORATIVOS" contiene
        // "ELEMENTOS", así que buscando al revés todo caería en 'elemento'.
        foreach (self::KINDS as $carpeta => $valor) {
            if (str_contains($rel, Str::upper(Str::ascii($carpeta)))) {
                $kind = $valor;

                if ($carpeta === 'ELEMENTOS DECORATIVOS' || $carpeta === 'SOMBRAS Y DEGRADADOS') {
                    break;
                }
            }
        }

        if ($kind === null) {
            return null;
        }

        foreach (self::TRADES as $carpeta => $valor) {
            if (str_contains($rel, $carpeta)) {
                return [$kind, $valor];
            }
        }

        return [$kind, null];
    }

    /**
     * El encuadre real del dibujo y si su tinta es clara u oscura.
     *
     * La claridad se mide SOLO sobre lo que no es transparente y sobre una
     * miniatura: en el lienzo entero el 95% son píxeles vacíos y el promedio
     * daría siempre lo mismo.
     *
     * @return array{x: int, y: int, w: int, h: int, ink: string|null}|null
     */
    private function medir(string $archivo): ?array
    {
        try {
            $im = new Imagick($archivo);
        } catch (\Throwable) {
            return null;
        }

        $im->setImageBackgroundColor(new ImagickPixel('transparent'));

        $recorte = clone $im;
        $recorte->trimImage(0);
        $page = $recorte->getImagePage();

        $caja = [
            'x' => (int) $page['x'],
            'y' => (int) $page['y'],
            'w' => $recorte->getImageWidth(),
            'h' => $recorte->getImageHeight(),
        ];

        $recorte->thumbnailImage(80, 80, true);
        $ancho = $recorte->getImageWidth();
        $alto = $recorte->getImageHeight();
        $pixeles = $recorte->exportImagePixels(0, 0, $ancho, $alto, 'RGBA', Imagick::PIXEL_CHAR);

        $suma = 0.0;
        $saturacion = 0.0;
        $cuenta = 0;

        for ($i = 0, $n = count($pixeles); $i + 3 < $n; $i += 4) {
            // Medio opaco no alcanza: los bordes suavizados de una letra
            // negra son grises, y contarlos corre el promedio al medio.
            if ($pixeles[$i + 3] < 200) {
                continue;
            }

            [$r, $g, $b] = [$pixeles[$i], $pixeles[$i + 1], $pixeles[$i + 2]];

            $suma += ($r * 0.299 + $g * 0.587 + $b * 0.114) / 255;

            // Cuánto se aleja del gris. Sin esto, un degradado VERDE oscuro
            // pasaba por "sombra" —su luz es baja— y se usaba de velo sobre
            // una foto: el post salía con la clienta teñida de verde. Pasó
            // de verdad, en el perfil de Patricia.
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $saturacion += $max === 0 ? 0.0 : ($max - $min) / $max;

            $cuenta++;
        }

        $recorte->destroy();
        $im->destroy();

        $luz = $cuenta === 0 ? 0.0 : $suma / $cuenta;
        $tono = $cuenta === 0 ? 0.0 : $saturacion / $cuenta;

        $caja['ink'] = match (true) {
            $cuenta === 0 => null,
            // Con color de verdad no es ni clara ni oscura, por más apagada
            // que sea: un esmalte rosa, un marco dorado, un degradado verde.
            // Se apartan porque no sirven para elegir por contraste ni para
            // hacer de sombra.
            $tono > 0.22 => 'color',
            $luz < 0.38 => 'dark',
            $luz > 0.68 => 'light',
            default => 'color',
        };

        return $caja;
    }
}
