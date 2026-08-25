<script setup>
/**
 * La marca de Glow Studios: la corona.
 *
 * Antes fue un loto, y antes de eso un destello, unas tijeras y un 💈. La
 * corona la pidió el dueño el 2026-08-22 y conserva todo lo demás del loto:
 * el mismo oro, el mismo trazo fino sin relleno, el mismo aire, y la misma
 * capacidad de dibujarse sola en la pantalla de carga (ver SplashScreen).
 *
 * Sigue sin ser la herramienta de ningún oficio, que era la razón de ser del
 * destello: vale igual para una manicurista que para un barbero.
 *
 * **El dibujo no vive aquí**, sino en `crown.js`, junto al de la pantalla de
 * carga y al del QR: así ninguna de las tres puede separarse de las otras al
 * retocar una sola. Lo único que pone esta marca es el recorte —que deja justo
 * la corona, sin el hueco que la pantalla de carga reserva para las letras— y
 * el grosor del trazo.
 */
import { CROWN_GOLD, CROWN_STROKES, CROWN_VIEWBOX } from '../../crown';

defineProps({
    size: { type: Number, default: 28 },
    // Un solo color plano, para cuando la marca va sobre un fondo de color y el
    // degradado cobrizo compite en vez de acompañar.
    flat: { type: String, default: '' },
});

// Los degradados viven en <defs> con id, así que dos marcas en la misma página
// chocarían y la segunda se rompería. Un id por instancia lo evita.
const uid = `crown-${Math.random().toString(36).slice(2, 9)}`;
</script>

<template>
    <svg
        :width="size"
        :height="size"
        :viewBox="CROWN_VIEWBOX"
        fill="none"
        aria-hidden="true"
        class="overflow-visible"
    >
        <defs v-if="!flat">
            <linearGradient :id="uid" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" :stop-color="CROWN_GOLD[0]" />
                <stop offset="45%" :stop-color="CROWN_GOLD[1]" />
                <stop offset="100%" :stop-color="CROWN_GOLD[2]" />
            </linearGradient>
        </defs>

        <g
            :stroke="flat || `url(#${uid})`"
            stroke-width="5.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            fill="none"
        >
            <path v-for="d in CROWN_STROKES" :key="d" :d="d" />
        </g>
    </svg>
</template>
