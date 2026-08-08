<script setup>
/**
 * Marca provisional de Glow Studios, hasta que exista el logo real.
 *
 * Reemplaza al 💈 y a las tijeras, que decían "barbería" en una app donde la
 * mayoría de las profesionales hacen uñas, pestañas o maquillaje.
 *
 * Es un destello, no una herramienta de ningún oficio: sirve igual para una
 * manicurista que para un barbero. SVG puro con degradado y un brillo interior
 * para darle volumen — sin imágenes externas, nítido a cualquier tamaño, y se
 * cambia por el logo definitivo tocando solo este archivo.
 */
defineProps({
    size: { type: Number, default: 28 },
});

// Los degradados viven en <defs> con id, así que dos marcas en la misma página
// chocarían. Un id por instancia lo evita.
const uid = `glow-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <defs>
            <linearGradient :id="`${uid}-tile`" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#3B3F5C" />
                <stop offset="55%" stop-color="#171A2C" />
                <stop offset="100%" stop-color="#0B0D18" />
            </linearGradient>
            <linearGradient :id="`${uid}-spark`" x1="0.2" y1="0" x2="0.8" y2="1">
                <stop offset="0%" stop-color="#FFF4D6" />
                <stop offset="45%" stop-color="#E8C877" />
                <stop offset="100%" stop-color="#C99B3F" />
            </linearGradient>
            <radialGradient :id="`${uid}-glow`" cx="0.5" cy="0.42" r="0.55">
                <stop offset="0%" stop-color="#E8C877" stop-opacity="0.45" />
                <stop offset="100%" stop-color="#E8C877" stop-opacity="0" />
            </radialGradient>
        </defs>

        <rect x="1" y="1" width="46" height="46" rx="13" :fill="`url(#${uid}-tile)`" />
        <!-- Bisel: una línea clara arriba y una oscura abajo bastan para que la
             ficha se lea con volumen sin recurrir a una sombra falsa. -->
        <rect x="1.5" y="1.5" width="45" height="45" rx="12.5" stroke="#FFFFFF" stroke-opacity="0.16" />
        <rect x="1.5" y="2.5" width="45" height="44" rx="12.5" stroke="#000000" stroke-opacity="0.35" />

        <circle cx="24" cy="22" r="15" :fill="`url(#${uid}-glow)`" />

        <!-- El destello de cuatro puntas: curvas cóncavas, que es lo que separa
             un brillo de una estrella infantil. -->
        <path
            d="M24 8c1.4 7.6 4.9 11.1 12.5 12.5C28.9 21.9 25.4 25.4 24 33c-1.4-7.6-4.9-11.1-12.5-12.5C19.1 19.1 22.6 15.6 24 8Z"
            :fill="`url(#${uid}-spark)`"
        />
        <path
            d="M35 30c.6 3.2 2.1 4.7 5.3 5.3-3.2.6-4.7 2.1-5.3 5.3-.6-3.2-2.1-4.7-5.3-5.3 3.2-.6 4.7-2.1 5.3-5.3Z"
            :fill="`url(#${uid}-spark)`"
            opacity="0.75"
        />
    </svg>
</template>
