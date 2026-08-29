<?php

namespace Tests\Unit;

use App\Actions\Content\BuildCollage;
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
            BuildCollage::cleanLines(['Resultados', 'de hoy', '✨']),
        );
    }

    public function test_symbols_are_stripped_but_the_words_survive(): void
    {
        $this->assertSame(
            ['CITAS ABIERTAS', 'YA'],
            BuildCollage::cleanLines(['✨ Citas abiertas ✨', '  ya  ']),
        );
    }

    public function test_accents_and_spanish_marks_are_kept(): void
    {
        $this->assertSame(
            ['¿QUÉ ESPERÁS?', 'AGENDÁ'],
            BuildCollage::cleanLines(['¿Qué esperás?', 'agendá']),
        );
    }

    public function test_at_most_three_lines(): void
    {
        $this->assertCount(3, BuildCollage::cleanLines(['una', 'dos', 'tres', 'cuatro']));
    }

    public function test_a_headline_of_only_symbols_leaves_nothing(): void
    {
        $this->assertSame([], BuildCollage::cleanLines(['✨', '🔥', '···']));
    }
}
