<?php

namespace Tests\Unit;

use App\Actions\Content\DrawHeadline;
use App\Actions\Content\HeadlinePhrases;
use App\Enums\BusinessCategory;
use PHPUnit\Framework\TestCase;

/**
 * Las frases del oficio que van impresas grandes sobre la foto. Antes el
 * titular lo inventaba el modelo y salía «UÑAS DE HOY / GLOW STUDIOS»: el
 * nombre del negocio gastando una línea que el sello ya muestra. Ver
 * HeadlinePhrases.
 */
class HeadlinePhrasesTest extends TestCase
{
    public function test_every_category_gets_phrases_and_never_an_empty_list(): void
    {
        // pick() usa array_rand(), que revienta con un array vacío.
        foreach ([...BusinessCategory::cases(), null] as $category) {
            $quien = $category?->value ?? 'sin rubro';

            $this->assertNotEmpty(HeadlinePhrases::forCategory($category), $quien.' sin frases');

            $frase = HeadlinePhrases::pick($category);

            $this->assertCount(2, $frase, $quien.' devolvió algo que no son dos líneas');
            $this->assertNotSame('', trim($frase[0]), $quien.' devolvió una línea vacía');
            $this->assertNotSame('', trim($frase[1]), $quien.' devolvió una línea vacía');
        }
    }

    public function test_a_trade_gets_its_own_vocabulary_on_top_of_the_general_ones(): void
    {
        $nails = HeadlinePhrases::forCategory(BusinessCategory::Nails);
        $hair = HeadlinePhrases::forCategory(BusinessCategory::Hair);

        $primeras = static fn (array $frases): array => array_map(static fn (array $f): string => $f[0], $frases);

        $this->assertContains('ACRILICAS', $primeras($nails));
        $this->assertContains('BALAYAGE', $primeras($hair));

        // Y ninguno se lleva el vocabulario del otro.
        $this->assertNotContains('BALAYAGE', $primeras($nails));
        $this->assertNotContains('ACRILICAS', $primeras($hair));

        // Las generales están en los dos: una manicurista también agradece.
        $this->assertContains('AGENDA', $primeras($nails));
        $this->assertContains('AGENDA', $primeras($hair));
    }

    public function test_without_a_trade_it_does_not_guess_a_service(): void
    {
        // Adivinar «uñas» sobre una foto de cabello ya pasó una vez y se vio
        // de inmediato: sin rubro cargado, solo frases que sirven a todos.
        $sinRubro = array_map(
            static fn (array $f): string => $f[0],
            HeadlinePhrases::forCategory(null),
        );

        $this->assertContains('AGENDA', $sinRubro);
        $this->assertNotContains('ACRILICAS', $sinRubro);
        $this->assertNotContains('BALAYAGE', $sinRubro);
    }

    public function test_every_phrase_survives_the_font_filter(): void
    {
        // El motor descarta lo que la tipografía no sabe dibujar. Una frase
        // de la biblioteca que se cayera ahí dejaría el post sin titular.
        foreach ([...BusinessCategory::cases(), null] as $category) {
            foreach (HeadlinePhrases::forCategory($category) as $frase) {
                $this->assertCount(
                    2,
                    DrawHeadline::cleanLines($frase),
                    'La frase "'.implode(' / ', $frase).'" pierde una línea al limpiarse.',
                );
            }
        }
    }
}
