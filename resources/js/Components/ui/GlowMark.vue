<script setup>
/**
 * La marca de Glow Studios: el loto.
 *
 * Sustituye al destello provisional que estuvo aquí desde agosto, y que a su
 * vez había sustituido al 💈 y a las tijeras. Este ya es el logo de verdad —
 * el mismo que existe en el archivo original, redibujado en trazos para que
 * sea nítido a cualquier tamaño, pese unos cientos de bytes y, sobre todo,
 * pueda dibujarse solo en la pantalla de carga (ver SplashScreen).
 *
 * Sigue siendo una flor y no la herramienta de ningún oficio, que era la razón
 * de ser del destello: vale igual para una manicurista que para un barbero.
 */
defineProps({
    size: { type: Number, default: 28 },
    // Un solo color plano, para cuando la marca va sobre un fondo de color y el
    // degradado cobrizo compite en vez de acompañar.
    flat: { type: String, default: '' },
});

// Los degradados viven en <defs> con id, así que dos marcas en la misma página
// chocarían y la segunda se rompería. Un id por instancia lo evita.
const uid = `lotus-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 200 148"
        fill="none"
        aria-hidden="true"
        class="overflow-visible"
    >
        <defs v-if="!flat">
            <linearGradient :id="uid" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#B4744A" />
                <stop offset="45%" stop-color="#F5D9BC" />
                <stop offset="100%" stop-color="#A9683F" />
            </linearGradient>
        </defs>

        <g
            :stroke="flat || `url(#${uid})`"
            stroke-width="7"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
        >
            <!-- Exteriores -->
            <path d="M100 132 C 68 132 30 122 8 94 C 40 84 82 104 100 132 Z" />
            <path d="M100 132 C 132 132 170 122 192 94 C 160 84 118 104 100 132 Z" />
            <!-- Medios -->
            <path d="M100 132 C 76 116 58 92 56 60 C 78 74 96 104 100 132 Z" />
            <path d="M100 132 C 124 116 142 92 144 60 C 122 74 104 104 100 132 Z" />
            <!-- Puntas -->
            <path d="M84 90 C 74 78 68 68 66 58 C 76 66 82 78 84 90" />
            <path d="M116 90 C 126 78 132 68 134 58 C 124 66 118 78 116 90" />
            <!-- Centro y detalle interior -->
            <path d="M100 132 C 84 108 82 70 100 42 C 118 70 116 108 100 132 Z" />
            <path d="M100 130 C 92 114 91 94 100 80 C 109 94 108 114 100 130 Z" />
        </g>
    </svg>
</template>
