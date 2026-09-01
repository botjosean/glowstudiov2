<?php

namespace App\Actions\Content;

use App\Models\ContentAsset;
use App\Models\Provider;
use App\Support\MediaUrl;
use Illuminate\Support\Carbon;

/**
 * Las bandejas de stickers del editor, en el orden en que le sirven.
 *
 * Ella eligió las cuatro: la técnica que se detectó, las de momento
 * (antes/proceso/después), las de agendar, y las de temporada.
 *
 * **Sobre la de temporada, la parte honesta:** el pack NO viene ordenado por
 * época — se lo dije antes de construir esto. Lo que se puede hacer con lo
 * que trae es agrupar por color: en septiembre salen primero los adornos
 * ámbar y terracota, en febrero los rosados. No es lo mismo que un pack de
 * temporada, pero es real y no inventa nada.
 */
class StickerTrays
{
    /**
     * Qué familia de color le toca a cada mes.
     *
     * Rangos de tono en grados, como los devuelve el importador. Elegidos a
     * ojo por lo que se asocia a cada época en el hemisferio norte, que es
     * donde ella trabaja (Atlanta).
     *
     * @var array<int, array{0: int, 1: int}>
     */
    private const SEASON_HUES = [
        1 => [180, 260],  // enero: azules fríos
        2 => [320, 360],  // febrero: rojos y rosas
        3 => [80, 160],   // marzo: verdes
        4 => [280, 340],  // abril: lilas
        5 => [300, 360],  // mayo: rosados
        6 => [40, 70],    // junio: amarillos
        7 => [180, 220],  // julio: celestes
        8 => [20, 50],    // agosto: dorados
        9 => [15, 45],    // septiembre: ámbar y terracota
        10 => [0, 35],    // octubre: naranjas
        11 => [10, 40],   // noviembre: tierras
        12 => [340, 20],  // diciembre: rojos
    ];

    /**
     * @return list<array{key: string, title: string, items: list<array<string, mixed>>}>
     */
    public function handle(Provider $provider, ?string $technique = null): array
    {
        $trade = $provider->business_category?->value;

        $todas = ContentAsset::query()
            ->where('kind', 'frase')
            ->forTrade($trade)
            ->get()
            ->filter(fn (ContentAsset $a): bool => PhraseSafety::usable($a->slug));

        $momento = PhraseSafety::manualOnly();

        $bandejas = [];

        // 1. La técnica detectada, si el pack la tiene escrita.
        if ($technique !== null) {
            $slug = \Illuminate\Support\Str::slug($technique);
            $suyas = $todas->filter(fn (ContentAsset $a): bool => $a->slug === $slug);

            if ($suyas->isNotEmpty()) {
                $bandejas[] = $this->tray('tecnica', $suyas);
            }
        }

        // 2. Las de momento: las que solo sabe ella. Son las que más le
        //    faltaban, así que van arriba.
        $bandejas[] = $this->tray(
            'momento',
            $todas->filter(fn (ContentAsset $a): bool => in_array($a->slug, $momento, true)),
        );

        // 3. Las de agendar: las que traen clientas.
        $agendar = ['reserva-tu-cita', 'turnos-disponibles', 'horarios-disponibles', 'politicas-de-reserva'];

        $bandejas[] = $this->tray(
            'agendar',
            $todas->filter(fn (ContentAsset $a): bool => $a->slug !== null
                && (str_starts_with($a->slug, 'agenda') || in_array($a->slug, $agendar, true))),
        );

        // 4. Temporada: adornos y marcos del color del mes.
        $bandejas[] = $this->tray('temporada', $this->seasonal($trade));

        // 5. Y el resto de las frases, para que nada quede escondido.
        $yaPuestas = collect($bandejas)->flatMap(fn (array $b): array => array_column($b['items'], 'id'))->all();

        $bandejas[] = $this->tray(
            'otras',
            $todas->reject(fn (ContentAsset $a): bool => in_array($a->id, $yaPuestas, true)),
        );

        return array_values(array_filter($bandejas, fn (array $b): bool => $b['items'] !== []));
    }

    /**
     * Adornos y marcos cuyo color pega con el mes.
     *
     * @return \Illuminate\Support\Collection<int, ContentAsset>
     */
    private function seasonal(?string $trade)
    {
        [$desde, $hasta] = self::SEASON_HUES[(int) Carbon::now()->format('n')] ?? [0, 360];

        return ContentAsset::query()
            ->whereIn('kind', ['decorativo', 'marco'])
            ->forTrade($trade)
            ->whereNotNull('hue')
            ->get()
            ->filter(function (ContentAsset $a) use ($desde, $hasta): bool {
                // Diciembre cruza el cero (340 a 20): ahí el rango va al
                // revés y hay que aceptar los dos lados.
                return $desde <= $hasta
                    ? $a->hue >= $desde && $a->hue <= $hasta
                    : $a->hue >= $desde || $a->hue <= $hasta;
            })
            ->take(24);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ContentAsset>  $assets
     * @return array{key: string, title: string, items: list<array<string, mixed>>}
     */
    private function tray(string $key, $assets): array
    {
        return [
            'key' => $key,
            'title' => 'content.tray_'.$key,
            'items' => $assets->values()->map(fn (ContentAsset $a): array => [
                'id' => $a->id,
                'url' => MediaUrl::resolve($a->path),
                'text' => $a->text,
                'ink' => $a->ink,
            ])->all(),
        ];
    }
}
