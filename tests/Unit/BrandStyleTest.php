<?php

namespace Tests\Unit;

use App\Actions\Content\BrandStyle;
use App\Actions\Content\CollageArrangement;
use App\Enums\BusinessCategory;
// La app arrancada y no PHPUnit a secas: headlineFont() usa resource_path(),
// y comprobar que la ruta de la fuente existe de verdad es justamente lo que
// caza un nombre de archivo mal escrito antes de que reviente al generar.
use Tests\TestCase;

class BrandStyleTest extends TestCase
{
    /**
     * Cada rubro tiene que quedarse SIEMPRE con al menos un armado, para
     * cualquier cantidad de fotos. Si la preferencia de un rubro no coincide
     * con ninguno de los que caben, devolver lista vacía haría reventar el
     * sorteo con "array_rand(): Argument #1 must not be empty".
     */
    public function test_every_category_always_gets_at_least_one_arrangement(): void
    {
        $categories = [...BusinessCategory::cases(), null];

        foreach ($categories as $category) {
            foreach (range(2, 12) as $count) {
                $available = CollageArrangement::optionsFor($count);
                $chosen = BrandStyle::preferredArrangements($category, $available);

                $etiqueta = ($category?->value ?? 'sin rubro')." con {$count} fotos";

                $this->assertNotEmpty($chosen, "{$etiqueta} se queda sin armados");

                // Y nunca uno que no quepa con esa cantidad.
                foreach ($chosen as $name) {
                    $this->assertContains($name, $available, "{$etiqueta}: {$name} no cabe");
                }
            }
        }
    }

    public function test_two_categories_that_show_work_differently_do_not_get_the_same_options(): void
    {
        // Un salón de uñas luce catálogo; un spa quiere aire. Con cuatro fotos
        // no deberían recibir el mismo reparto.
        $available = CollageArrangement::optionsFor(4);

        $this->assertNotSame(
            BrandStyle::preferredArrangements(BusinessCategory::Nails, $available),
            BrandStyle::preferredArrangements(BusinessCategory::SpaMassage, $available),
        );
    }

    public function test_every_category_has_its_own_eyebrows_and_palette(): void
    {
        foreach (BusinessCategory::cases() as $category) {
            $this->assertNotEmpty(BrandStyle::eyebrows($category), $category->value.' sin antetítulos');
            $this->assertCount(3, BrandStyle::blockColors($category), $category->value.' sin paleta completa');
            $this->assertNotEmpty(BrandStyle::headlineStyles($category), $category->value.' sin estilos');
            $this->assertFileExists(BrandStyle::headlineFont($category), $category->value.' apunta a una fuente que no existe');
        }
    }
}
