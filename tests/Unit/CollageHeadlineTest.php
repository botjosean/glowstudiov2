<?php

namespace Tests\Unit;

use App\Actions\Content\DrawHeadline;
use PHPUnit\Framework\TestCase;

class CollageHeadlineTest extends TestCase
{
    /**
     * Reproduce un post real (29-ago) donde quedó un bloque de color vacío
     * colgando debajo del titular: el modelo mandó una tercera línea con un
     * emoji, Anton no lo dibuja, pero la línea igual pintaba su fondo.
     */
    public function test_a_line_with_nothing_the_font_can_draw_is_dropped(): void
    {
        $this->assertSame(
            ['RESULTADOS', 'DE HOY'],
            DrawHeadline::cleanLines(['Resultados', 'de hoy', '✨']),
        );
    }

    public function test_symbols_are_stripped_but_the_words_survive(): void
    {
        $this->assertSame(
            ['CITAS ABIERTAS', 'YA'],
            DrawHeadline::cleanLines(['✨ Citas abiertas ✨', '  ya  ']),
        );
    }

    public function test_accents_and_spanish_marks_are_kept(): void
    {
        $this->assertSame(
            ['¿QUÉ ESPERÁS?', 'AGENDÁ'],
            DrawHeadline::cleanLines(['¿Qué esperás?', 'agendá']),
        );
    }

    public function test_at_most_three_lines(): void
    {
        $this->assertCount(3, DrawHeadline::cleanLines(['una', 'dos', 'tres', 'cuatro']));
    }

    public function test_a_headline_of_only_symbols_leaves_nothing(): void
    {
        $this->assertSame([], DrawHeadline::cleanLines(['✨', '🔥', '···']));
    }

    /**
     * Reproduce un post real de Josean (29-ago): su ficha de estilo trae
     * blanco como uno de los tres colores, y el texto de 'blocks' se pintaba
     * siempre blanco — un bloque en blanco sin letra visible encima.
     */
    public function test_dark_text_on_a_light_block_so_it_stays_readable(): void
    {
        $this->assertSame('#111827', DrawHeadline::textColorFor('#FFFFFF'));
        $this->assertSame('#111827', DrawHeadline::textColorFor('#DCC9B7'));
    }

    public function test_white_text_on_a_dark_block_as_before(): void
    {
        $this->assertSame('#ffffff', DrawHeadline::textColorFor('#000000'));
        $this->assertSame('#ffffff', DrawHeadline::textColorFor('#111827'));
    }
}
