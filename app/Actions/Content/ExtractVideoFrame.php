<?php

namespace App\Actions\Content;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Saca un fotograma de un video de referencia para que el modelo de visión
 * pueda leerlo igual que a una foto.
 *
 * No existe, en esta app, una IA que "mire" video directamente — el mismo
 * principio de siempre: se le da al modelo una imagen fija y se le pide que
 * la describa, nunca que procese el clip entero.
 *
 * Se toma cerca del arranque (medio segundo) y no del medio del video: en
 * los clips de tendencia el texto de diseño suele aparecer desde el primer
 * fotograma y quedarse fijo el resto del video, así que ahí ya está lo que
 * hace falta leer, sin necesidad de calcular la duración primero.
 */
class ExtractVideoFrame
{
    /**
     * @return string|null el JPEG en binario, o null si no se pudo sacar
     */
    public function handle(string $videoBinary): ?string
    {
        $dir = sys_get_temp_dir();
        $in = $dir.'/'.Str::random(24).'.mp4';
        $out = $dir.'/'.Str::random(24).'.jpg';

        file_put_contents($in, $videoBinary);

        try {
            $result = Process::timeout(20)->run([
                'ffmpeg', '-y',
                '-ss', '00:00:00.5',
                '-i', $in,
                '-frames:v', '1',
                '-vf', 'scale=720:-1',
                $out,
            ]);

            if (! $result->successful() || ! is_file($out)) {
                Log::warning('No se pudo sacar un fotograma del video de referencia.', [
                    'error' => mb_substr($result->errorOutput(), -500),
                ]);

                return null;
            }

            return file_get_contents($out) ?: null;
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
