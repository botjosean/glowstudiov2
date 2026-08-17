<script setup>
/**
 * El hueco gris que ocupa el sitio de algo que todavía no llegó.
 *
 * No acelera nada: hace que la espera se lea como "esto viene" en vez de como
 * "aquí no hay nada". La diferencia importa cuando la profesional abre el alta
 * de una cita en el salón con mal wifi y la rejilla de horas tarda un segundo —
 * antes veía un texto suelto que decía "cargando"; ahora ve la forma de lo que
 * está por aparecer.
 *
 * El brillo que lo recorre se apaga entero con "reducir movimiento": una
 * animación en bucle es exactamente lo que molesta a quien activa esa opción.
 */
defineProps({
    // Alto en píxeles; el ancho lo pone quien lo usa con clases.
    height: { type: Number, default: 44 },
    rounded: { type: String, default: 'rounded-xl' },
});
</script>

<template>
    <div
        class="skeleton w-full bg-[var(--surface-mute)]"
        :class="rounded"
        :style="{ height: `${height}px` }"
        aria-hidden="true"
    />
</template>

<style scoped>
.skeleton {
    position: relative;
    overflow: hidden;
}

.skeleton::after {
    content: '';
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(
        90deg,
        transparent,
        color-mix(in srgb, var(--surface) 55%, transparent),
        transparent
    );
    animation: sheen 1.4s ease-in-out infinite;
}

@keyframes sheen {
    to { transform: translateX(100%); }
}

@media (prefers-reduced-motion: reduce) {
    .skeleton::after { animation: none; }
}
</style>
