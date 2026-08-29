<script setup>
import { CROWN_FILLS, CROWN_GOLD, CROWN_STROKES, CROWN_VIEWBOX } from '../../crown';

/**
 * La pantalla que tapa todo mientras se sube o se arma algo.
 *
 * Nace de un reporte concreto: al darle guardar, la hoja se quedaba quieta y
 * parecía trabada. Con fotos de teléfono por datos móviles eso son varios
 * segundos sin ninguna señal, y sin señal la reacción es volver a tocar el
 * botón.
 *
 * La corona es la misma de `crown.js` que ya usan la barra y la pantalla de
 * arranque — no una segunda versión que pueda quedarse atrás. Acá no se
 * dibuja trazo a trazo como en el arranque: respira, nada más. Esa animación
 * es la bienvenida de la app y repetirla en cada subida la gastaría.
 *
 * El porcentaje solo sale cuando el navegador lo informa de verdad; mientras
 * no lo haga, la barra se queda indeterminada en vez de inventar un número.
 */
defineProps({
    show: { type: Boolean, default: false },
    percent: { type: Number, default: null },
    label: { type: String, required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-300"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-[90] flex flex-col items-center justify-center gap-6 bg-[var(--bg-canvas)]/95 px-10 backdrop-blur-sm"
                role="status"
                :aria-label="label"
            >
                <svg :viewBox="CROWN_VIEWBOX" class="crown w-[52%] max-w-[210px] overflow-visible">
                    <defs>
                        <linearGradient id="upload-gold" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" :stop-color="CROWN_GOLD[0]" />
                            <stop offset="45%" :stop-color="CROWN_GOLD[1]" />
                            <stop offset="100%" :stop-color="CROWN_GOLD[2]" />
                        </linearGradient>
                    </defs>

                    <g stroke="url(#upload-gold)" fill="none" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path v-for="d in CROWN_STROKES" :key="d" :d="d" />
                    </g>
                    <g fill="url(#upload-gold)">
                        <path v-for="d in CROWN_FILLS" :key="d" :d="d" />
                    </g>
                </svg>

                <div class="flex w-full max-w-[280px] flex-col items-center gap-2.5">
                    <div class="text-[15px] font-semibold text-[var(--text-strong)]">{{ label }}</div>

                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-[var(--surface-mute)]">
                        <div
                            v-if="percent !== null"
                            class="h-full rounded-full bg-[var(--gold)] transition-[width] duration-200"
                            :style="{ width: `${percent}%` }"
                        />
                        <div v-else class="sweep h-full w-2/5 rounded-full bg-[var(--gold)]" />
                    </div>

                    <div v-if="percent !== null" class="text-[12px] font-medium tabular-nums text-[var(--text-mute)]">
                        {{ percent }}%
                    </div>
                    <div v-else-if="hint" class="text-center text-[12px] font-normal text-[var(--text-mute)]">
                        {{ hint }}
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.crown {
    animation: breathe 2.4s ease-in-out infinite;
}

@keyframes breathe {
    0%, 100% { opacity: 0.75; transform: scale(0.98); }
    50%      { opacity: 1;    transform: scale(1.02); }
}

/* Barra indeterminada: mientras el navegador no informe progreso real. */
.sweep {
    animation: sweep 1.3s ease-in-out infinite;
}

@keyframes sweep {
    0%   { transform: translateX(-110%); }
    100% { transform: translateX(360%); }
}

@media (prefers-reduced-motion: reduce) {
    .crown, .sweep { animation: none; }
}
</style>
