<?php

namespace App\Actions\Content;

use App\Models\Provider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda un video de referencia tal cual llegó.
 *
 * **A propósito NO pasa por StoreProviderImage.** Esa acción reencoda todo a
 * WebP, que es justamente su defensa de seguridad para imágenes — pero a un
 * video lo destruiría. Aquí no hay reencodado que sirva de filtro, así que
 * la defensa es otra: la extensión y el tipo los pone el servidor a partir
 * de una lista cerrada, el nombre del archivo del teléfono se descarta, y R2
 * sirve estos objetos como descarga, nunca ejecutados.
 */
class StoreReferenceVideo
{
    /**
     * Contenedores que un teléfono produce de verdad. Cerrado a propósito:
     * cualquier otra cosa se rechaza antes de llegar acá (ver el Request).
     */
    private const TYPES = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/x-m4v' => 'm4v',
        'video/webm' => 'webm',
    ];

    public function handle(Provider $provider, UploadedFile $file): string
    {
        $mime = (string) $file->getMimeType();
        $extension = self::TYPES[$mime] ?? 'mp4';

        $key = sprintf('providers/%d/referencias/%s.%s', $provider->id, (string) Str::ulid(), $extension);

        // Por stream y no con getContent(): un video de decenas de MB leído
        // entero a memoria se lleva por delante el memory_limit de 256 MB del
        // contenedor. Pasando el recurso, el driver de S3 lo sube por partes.
        $stream = fopen($file->getRealPath(), 'rb');

        try {
            Storage::disk('r2')->put($key, $stream, [
                'ContentType' => $mime,
                'CacheControl' => 'public, max-age=31536000, immutable',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $key;
    }

    /** @return list<string> */
    public static function allowedMimes(): array
    {
        return array_keys(self::TYPES);
    }
}
