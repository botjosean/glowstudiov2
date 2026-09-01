<?php

namespace Tests\Unit;

use App\Actions\Content\PhraseSafety;
use Tests\TestCase;

/**
 * Qué puede estampar el sistema solo.
 *
 * Ella lo señaló mirando posts reales: «tú no sabes cuándo la foto está
 * terminada o cuándo está empezando el proceso [...] al otro le pones
 * proceso y de repente no, ya eso es terminado». Tenía razón, y no es de
 * afinar el modelo: ese dato no está en la foto.
 */
class PhraseSafetyTest extends TestCase
{
    public function test_a_phrase_that_claims_a_moment_is_never_stamped_alone(): void
    {
        foreach (['antes', 'despues', 'proceso', 'resultado-final', 'antes-y-despues', 'estamos-atendiendo'] as $slug) {
            $this->assertFalse(PhraseSafety::autoSafe($slug), "«{$slug}» no debería estamparse sola.");
        }
    }

    public function test_a_technique_name_is_always_safe(): void
    {
        // El nombre de la técnica es verdad siempre que la técnica sea la
        // correcta, y eso ya se sabe de la foto.
        foreach (['balayage', 'acrilicas', 'microblading', 'pedicura', 'babylights'] as $slug) {
            $this->assertTrue(PhraseSafety::autoSafe($slug));
        }
    }

    public function test_a_call_to_book_is_always_safe(): void
    {
        foreach (['agenda-abierta', 'reserva-tu-cita', 'turnos-disponibles', 'clienta-feliz'] as $slug) {
            $this->assertTrue(PhraseSafety::autoSafe($slug));
        }
    }

    public function test_the_broken_ones_are_refused_everywhere(): void
    {
        // "Traicional" viene mal escrita del pack. No la pone ni el sistema
        // ni ella: no hay contexto en que un error de ortografía sirva.
        foreach (PhraseSafety::broken() as $slug) {
            $this->assertFalse(PhraseSafety::autoSafe($slug));
            $this->assertFalse(PhraseSafety::usable($slug));
        }
    }

    public function test_a_moment_phrase_is_still_offered_to_her_by_hand(): void
    {
        // No se borran: ella sí sabe si la foto es el antes o el después.
        $this->assertTrue(PhraseSafety::usable('antes'));
        $this->assertTrue(PhraseSafety::usable('proceso'));
    }

    public function test_a_phrase_without_text_read_is_not_risked(): void
    {
        $this->assertFalse(PhraseSafety::autoSafe(null));
        $this->assertFalse(PhraseSafety::autoSafe(''));
    }
}
