<?php

namespace Tests\Unit;

use App\Actions\Content\ExtractVideoFrame;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Sacarle un fotograma a un video de referencia es lo que le permite al
 * modelo de visión leer la tendencia de letras que solo guardó en video —
 * antes esa parte quedaba completamente ciega para el sistema.
 */
class ExtractVideoFrameTest extends TestCase
{
    private function unVideoDePrueba(): string
    {
        $out = sys_get_temp_dir().'/glow-test-clip.mp4';

        // Un clip sintético de un segundo, generado en el momento: no hace
        // falta guardar un archivo binario en el repo para esta prueba.
        Process::timeout(20)->run([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'color=c=blue:s=64x64:d=1',
            $out,
        ]);

        return file_get_contents($out);
    }

    public function test_it_pulls_a_readable_frame_out_of_a_video(): void
    {
        $frame = app(ExtractVideoFrame::class)->handle($this->unVideoDePrueba());

        $this->assertNotNull($frame);
        // Los primeros tres bytes de un JPEG son siempre este marcador.
        $this->assertSame("\xFF\xD8\xFF", substr($frame, 0, 3));
    }

    public function test_garbage_that_is_not_a_video_returns_null_instead_of_crashing(): void
    {
        $frame = app(ExtractVideoFrame::class)->handle('esto no es un video, es texto suelto');

        $this->assertNull($frame);
    }
}
