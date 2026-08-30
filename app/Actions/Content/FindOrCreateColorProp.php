<?php

namespace App\Actions\Content;

use App\Enums\BusinessCategory;
use App\Models\ColorProp;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * La foto decorativa a juego con un color, cacheada por rubro y por "cajón"
 * de color: se genera una sola vez y le sirve a cualquier proveedora que
 * caiga en ese mismo rubro y familia de color, para siempre.
 *
 * Con ~26 colores del catálogo (ver ColorNames) y once rubros, el techo del
 * caché es conocido: como mucho 286 generaciones en la vida entera de la
 * app, nunca una por post.
 */
class FindOrCreateColorProp
{
    public function __construct(
        private readonly GeneratePropImage $generate,
    ) {}

    /**
     * @return string|null la clave de R2 de la foto decorativa, o null si
     *                      nunca se pudo generar (nunca bloquea el post)
     */
    public function handle(BusinessCategory $category, string $colorName, string $colorHex): ?string
    {
        $colorKey = ColorNames::nearestKey($colorHex);

        $existente = ColorProp::query()
            ->where('business_category', $category->value)
            ->where('color_key', $colorKey)
            ->first();

        if ($existente !== null) {
            return $existente->path;
        }

        $bytes = $this->generate->handle($colorName, $colorHex);

        if ($bytes === null) {
            return null;
        }

        $path = sprintf('props/%s/%s.png', $category->value, (string) Str::ulid());

        Storage::disk('r2')->put($path, $bytes, [
            'ContentType' => 'image/png',
            'CacheControl' => 'public, max-age=31536000, immutable',
        ]);

        // Si dos posts pidieron el mismo color al mismo tiempo, la
        // restricción única del catálogo evita dos filas: se guarda la
        // primera y la segunda foto generada de más simplemente no se usa
        // en ninguna parte, sin romper nada.
        ColorProp::query()->firstOrCreate(
            ['business_category' => $category->value, 'color_key' => $colorKey],
            ['path' => $path],
        );

        return $path;
    }
}
