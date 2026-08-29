<?php

namespace Tests\Unit;

use App\Actions\Content\CollageArrangement;
use PHPUnit\Framework\TestCase;

class CollageArrangementTest extends TestCase
{
    /**
     * Cada armado tiene que colocar TODAS las fotos y llenar el cuadrado sin
     * huecos ni superposiciones. Un armado con un rectángulo de menos deja una
     * foto afuera en silencio, que ya pasó antes.
     */
    public function test_every_arrangement_places_every_photo_and_fills_the_canvas(): void
    {
        foreach (range(2, 12) as $count) {
            foreach (CollageArrangement::optionsFor($count) as $name) {
                $rects = CollageArrangement::rects($name, $count);

                $this->assertCount($count, $rects, "{$name} con {$count} fotos no coloca todas");

                $area = 0.0;

                foreach ($rects as [$x, $y, $w, $h]) {
                    $this->assertGreaterThan(0, $w, "{$name}: ancho cero");
                    $this->assertGreaterThan(0, $h, "{$name}: alto cero");
                    $this->assertLessThanOrEqual(1.0001, $x + $w, "{$name} con {$count}: se sale por la derecha");
                    $this->assertLessThanOrEqual(1.0001, $y + $h, "{$name} con {$count}: se sale por abajo");

                    $area += $w * $h;
                }

                // Suma 1 = cubre el cuadrado exacto. Sin solapes, porque
                // ninguno se sale y entre todos suman justo el área.
                $this->assertEqualsWithDelta(1.0, $area, 0.001, "{$name} con {$count} fotos deja hueco o se solapa");
            }
        }
    }

    public function test_there_is_more_than_one_way_to_lay_out_four_photos(): void
    {
        // La queja que originó todo esto: siempre salía la misma rejilla.
        $this->assertGreaterThan(3, count(CollageArrangement::optionsFor(4)));
    }
}
