<?php

namespace App\Console\Commands;

use App\Actions\Content\ColorNames;
use App\Actions\Content\FindOrCreateColorProp;
use App\Enums\BusinessCategory;
use App\Models\ColorProp;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Genera de una todas las fotos decorativas que le faltan a un rubro, en vez
 * de esperar a que un post pida cada color por primera vez.
 *
 * **Por qué existe.** Las decorativas se cachean por rubro y color, así que
 * cada una se paga UNA sola vez en la vida de la app. Generarlas por
 * adelantado no cambia el total —son las mismas— pero sí cambia dos cosas
 * que importan: el primer post de cada color deja de esperar la llamada, y
 * el gasto entero se puede hacer AHORA, mientras haya crédito, en vez de ir
 * goteando meses adelante.
 *
 * Con veintiséis colores en el catálogo, un rubro entero cuesta alrededor de
 * un dólar y queda guardado para siempre.
 *
 * Se corre a mano, nunca en el planificador: es la única parte del taller
 * que gasta sin que nadie lo haya pedido.
 */
#[Signature('content:preparar-decorados {rubro : El rubro, por ejemplo nails o hair} {--forzar : Generar también las que ya existen}')]
#[Description('Genera por adelantado las fotos decorativas de todos los colores de un rubro.')]
class PrepareColorProps extends Command
{
    public function handle(FindOrCreateColorProp $props): int
    {
        $rubro = BusinessCategory::tryFrom((string) $this->argument('rubro'));

        if ($rubro === null) {
            $this->error('Rubro desconocido. Los válidos son: '.implode(', ', array_column(BusinessCategory::cases(), 'value')));

            return self::FAILURE;
        }

        $catalogo = ColorNames::all();

        // Se pregunta antes: son llamadas que cuestan plata de verdad y no
        // las pidió ninguna clienta.
        $yaHechas = ColorProp::query()->where('business_category', $rubro->value)->count();
        $faltan = $this->option('forzar') ? count($catalogo) : count($catalogo) - $yaHechas;

        if ($faltan <= 0) {
            $this->info("Ya están las {$yaHechas} de {$rubro->value}. Nada que hacer.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Se van a generar hasta {$faltan} fotos decorativas para {$rubro->value}. ¿Seguir?", true)) {
            return self::SUCCESS;
        }

        $hechas = 0;
        $fallidas = 0;

        foreach ($catalogo as $hex => [$nombreEs]) {
            if ($this->option('forzar')) {
                ColorProp::query()
                    ->where('business_category', $rubro->value)
                    ->where('color_key', $hex)
                    ->delete();
            }

            $path = $props->handle($rubro, $nombreEs, $hex);

            if ($path === null) {
                $this->warn("  ✗ {$nombreEs} ({$hex})");
                $fallidas++;

                continue;
            }

            $this->line("  ✓ {$nombreEs} ({$hex})");
            $hechas++;
        }

        $this->newLine();
        $this->info("Listas: {$hechas}. Fallidas: {$fallidas}.");

        // Una falla suelta no es un problema: el color que no se pudo generar
        // se vuelve a intentar solo la próxima vez que un post lo pida.
        return self::SUCCESS;
    }
}
