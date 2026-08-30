<?php

namespace Tests\Unit;

use App\Actions\Content\BrandStyle;
use App\Enums\BusinessCategory;
use Tests\TestCase;

/**
 * La ficha leída de sus referencias tiene que ganarle a lo que dicta el
 * rubro. El rubro es una suposición razonable; la ficha es lo que ella de
 * verdad guardó como «así me gusta».
 */
class ContentStyleTest extends TestCase
{
    private function ficha(array $overrides = []): array
    {
        return array_merge([
            'colores' => ['#0b0f19', '#c9a227', '#ffffff'],
            'posicion_texto' => 'abajo',
            'tipografia' => 'serif',
            'estilo_titular' => 'franja',
        ], $overrides);
    }

    public function test_the_read_palette_beats_the_category_default(): void
    {
        $porRubro = BrandStyle::blockColorsFor(BusinessCategory::Nails, null);
        $porFicha = BrandStyle::blockColorsFor(BusinessCategory::Nails, $this->ficha());

        $this->assertNotSame($porRubro, $porFicha);
        $this->assertSame(['#0b0f19', '#c9a227', '#ffffff'], $porFicha);
    }

    public function test_two_colours_are_padded_to_three(): void
    {
        // El titular alterna entre tres; con dos quedaría un patrón raro.
        $colores = BrandStyle::blockColorsFor(null, $this->ficha(['colores' => ['#111111', '#eeeeee']]));

        $this->assertCount(3, $colores);
        $this->assertSame('#111111', $colores[2]);
    }

    public function test_the_read_font_beats_the_category_default(): void
    {
        // Uñas van con condensada por rubro; si sus referencias son serif,
        // manda la serif.
        $this->assertStringContainsString(
            'Playfair',
            BrandStyle::headlineFontFor(BusinessCategory::Nails, $this->ficha()),
        );
    }

    public function test_a_read_headline_style_weighs_the_bag_but_leaves_room_for_variety(): void
    {
        // Antes era uno solo y nada más — y eso fue el error real: con la
        // ficha marcando "limpio", cada post le salía exactamente igual al
        // anterior, para siempre. Ella lo pidió con las mismas palabras que
        // usó para las plantillas: «no hay combinación de letras cursivas».
        // Ahora pesa más, pero no es el único que puede salir.
        $bolsa = BrandStyle::headlineStylesFor(BusinessCategory::Nails, $this->ficha());

        $this->assertContains('band', $bolsa);
        $this->assertContains('cursiva', $bolsa);
        $this->assertContains('resaltado', $bolsa);
        // Pesa más: aparece más veces que cualquier otro estilo suelto.
        $this->assertGreaterThan(1, array_count_values($bolsa)['band']);
    }

    public function test_the_read_position_is_honoured(): void
    {
        $this->assertSame('bottom', BrandStyle::headlineSpotFor($this->ficha()));
        $this->assertSame('top', BrandStyle::headlineSpotFor($this->ficha(['posicion_texto' => 'arriba'])));
    }

    public function test_without_a_card_everything_falls_back_to_the_category(): void
    {
        $this->assertSame(
            BrandStyle::blockColors(BusinessCategory::Barbershop),
            BrandStyle::blockColorsFor(BusinessCategory::Barbershop, null),
        );

        // Sin posición leída se sortea, y eso lo señala devolviendo null.
        $this->assertNull(BrandStyle::headlineSpotFor(null));
    }

    public function test_a_read_cursiva_style_is_weighted_in(): void
    {
        // La tendencia que ella pidió imitar: script apilada con la gruesa.
        // Ver App\Actions\Content\DrawHeadline::draw().
        $bolsa = BrandStyle::headlineStylesFor(BusinessCategory::Nails, $this->ficha(['estilo_titular' => 'cursiva']));

        $this->assertGreaterThan(1, array_count_values($bolsa)['cursiva']);
        $this->assertContains('blocks', $bolsa);
    }

    public function test_cursiva_is_offered_for_most_rubros_but_not_barbershop(): void
    {
        $this->assertContains('cursiva', BrandStyle::headlineStyles(BusinessCategory::Nails));
        $this->assertContains('cursiva', BrandStyle::headlineStyles(BusinessCategory::Hair));
        $this->assertContains('cursiva', BrandStyle::headlineStyles(null));

        // Marca dura, sin script: no es lo que ellos mismos publicarían.
        $this->assertNotContains('cursiva', BrandStyle::headlineStyles(BusinessCategory::Barbershop));
        $this->assertNotContains('cursiva', BrandStyle::headlineStyles(BusinessCategory::TattooPiercing));
    }

    public function test_a_read_resaltado_style_is_weighted_in(): void
    {
        // Texto blanco con una palabra en color de acento, sin recuadro —
        // la otra tendencia de video que ella señaló. Ver
        // DrawHeadline::highlightedLine().
        $bolsa = BrandStyle::headlineStylesFor(BusinessCategory::Nails, $this->ficha(['estilo_titular' => 'resaltado']));

        $this->assertGreaterThan(1, array_count_values($bolsa)['resaltado']);
        $this->assertContains('band', $bolsa);
    }

    public function test_a_half_broken_card_falls_back_field_by_field(): void
    {
        // El modelo puede acertar los colores y no la tipografía. Cada campo
        // se decide solo, en vez de descartar la ficha entera.
        $rota = ['colores' => ['#111111', '#eeeeee'], 'tipografia' => null, 'posicion_texto' => 'vertical'];

        $this->assertSame(['#111111', '#eeeeee', '#111111'], BrandStyle::blockColorsFor(null, $rota));
        $this->assertSame(BrandStyle::headlineFont(BusinessCategory::Hair), BrandStyle::headlineFontFor(BusinessCategory::Hair, $rota));
        $this->assertNull(BrandStyle::headlineSpotFor($rota));
    }
}
