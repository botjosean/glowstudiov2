<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * La pantalla con la que abre la app: el loto se dibuja solo.
 *
 * Elegida por el dueño el 2026-08-17 entre ocho variantes. El trazo va de
 * afuera hacia el centro, que es como se abre una flor, y un reflejo recorre
 * el logo entero —flor y letras— mientras se dibuja; al final vuelve en un
 * destello corto, cuando ya está todo en pantalla.
 *
 * **El reflejo va sobre una copia del logo, no encima del fondo.** Esa copia
 * lleva las mismas animaciones de trazo, así que la luz sólo puede iluminar lo
 * que ya está dibujado: nunca se adelanta al lápiz.
 *
 * **Cuándo se ve**, que importa tanto como cómo se ve:
 *
 * - **Siempre que se abre la app**, instalada o en el navegador, y también al
 *   recargar. La primera versión sólo lo mostraba una vez por sesión y sólo en
 *   el panel: el dueño lo pidió cambiado el 2026-08-17 porque el logo
 *   desaparecía y no volvía.
 * - **No al cambiar de pestaña dentro de la app.** Las capas se vuelven a
 *   montar en cada navegación de Inertia, así que sin freno la animación se
 *   repetiría en cada toque de la barra inferior.
 * - **No en la página pública de una profesional** (`disabled`). Ahí llega una
 *   clienta desde un enlace de WhatsApp queriendo ver a Pati, y esa página ya
 *   la saluda con su nombre: dos bienvenidas seguidas son cuatro segundos
 *   antes de lo que vino a hacer.
 * - Nunca para quien pidió menos movimiento (prefers-reduced-motion).
 */
const props = defineProps({
    disabled: { type: Boolean, default: false },
});
const DURATION = 3200;

const visible = ref(false);
const leaving = ref(false);

/**
 * Ya se vio en ESTA carga de la página.
 *
 * Una variable de módulo, no sessionStorage, y la diferencia es justo el fallo
 * que tenía: sessionStorage sobrevive a cerrar el navegador —Chrome restaura la
 * pestaña con su almacenamiento intacto— así que el logo aparecía una vez y no
 * volvía nunca. Esto vive lo que vive el bundle: se borra en cualquier carga
 * completa, y no en una navegación interna.
 *
 * Resultado: sale al abrir la app y al recargar, y NO sale al cambiar de
 * pestaña dentro de la app — donde las capas se vuelven a montar y si no
 * repetiría la animación en cada toque de la barra inferior.
 */
let shownThisLoad = false;

let timers = [];

onMounted(() => {
    // Quien pidió menos movimiento no quiere tres segundos de animación cada
    // vez que abre.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    if (shownThisLoad || props.disabled) {
        return;
    }

    shownThisLoad = true;
    visible.value = true;
    // El fondo del documento no puede desplazarse debajo de la pantalla.
    document.documentElement.style.overflow = 'hidden';

    timers.push(setTimeout(() => { leaving.value = true; }, DURATION));
    timers.push(setTimeout(() => {
        visible.value = false;
        document.documentElement.style.overflow = '';
    }, DURATION + 420));
});

onUnmounted(() => {
    timers.forEach(clearTimeout);
    document.documentElement.style.overflow = '';
});

// Los ocho trazos, de afuera hacia el centro.
const PETALS = [
    'M100 116 C 68 116 30 106 8 78 C 40 68 82 88 100 116 Z',
    'M100 116 C 132 116 170 106 192 78 C 160 68 118 88 100 116 Z',
    'M100 116 C 76 100 58 76 56 44 C 78 58 96 88 100 116 Z',
    'M100 116 C 124 100 142 76 144 44 C 122 58 104 88 100 116 Z',
    'M84 74 C 74 62 68 52 66 42 C 76 50 82 62 84 74',
    'M116 74 C 126 62 132 52 134 42 C 124 50 118 62 116 74',
    'M100 116 C 84 92 82 54 100 26 C 118 54 116 92 100 116 Z',
    'M100 114 C 92 98 91 78 100 64 C 109 78 108 98 100 114 Z',
];

// El polvo dorado del logo original: denso a la derecha, suelto abajo a la
// izquierda, y un par sueltos arriba para que no quede todo de un lado.
const SPARKS = [
    [152, 30, 0.7], [161, 38, 1.1], [168, 31, 0.6], [157, 47, 1.5], [172, 45, 0.8],
    [164, 56, 1.9], [178, 55, 0.7], [169, 66, 1.2], [181, 68, 1.6], [159, 70, 0.6],
    [175, 79, 1], [186, 82, 0.9], [166, 86, 1.4], [179, 94, 1.2], [190, 73, 0.7],
    [154, 60, 0.8], [184, 60, 0.6], [171, 101, 0.9], [188, 100, 0.7],
    [46, 104, 1.3], [33, 112, 0.9], [22, 99, 1.6], [54, 116, 0.7], [15, 88, 1],
    [40, 95, 0.6], [28, 120, 1.1], [60, 108, 0.8], [9, 105, 0.7],
    [78, 26, 0.7], [120, 22, 0.9], [64, 36, 0.6],
];

// Nada de medir longitudes: pathLength="1" le pide al navegador que trate
// cada trazo como si midiera 1, así el mismo dash de 1 cubre exactamente el
// camino de los ocho, largos o cortos. Medirlos con getTotalLength() obligaría
// a montar el SVG antes de animarlo, y el primer fotograma se vería con la
// flor ya dibujada.
</script>

<template>
    <Transition leave-active-class="transition-opacity duration-[400ms]" leave-to-class="opacity-0">
        <div
            v-if="visible"
            class="splash fixed inset-0 z-[100] flex items-center justify-center"
            :class="leaving && 'pointer-events-none'"
            role="status"
            aria-label="Glow Studios"
        >
            <svg viewBox="0 0 200 200" class="w-[64%] max-w-[300px] overflow-visible">
                <defs>
                    <linearGradient id="splash-gold" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stop-color="#B4744A" />
                        <stop offset="45%" stop-color="#F5D9BC" />
                        <stop offset="100%" stop-color="#A9683F" />
                    </linearGradient>
                    <linearGradient id="splash-band" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#fff" stop-opacity="0" />
                        <stop offset="50%" stop-color="#fff" stop-opacity="1" />
                        <stop offset="100%" stop-color="#fff" stop-opacity="0" />
                    </linearGradient>
                    <!-- El barrido largo, mientras se dibuja. -->
                    <mask id="splash-sheen" maskUnits="userSpaceOnUse" x="0" y="0" width="200" height="200">
                        <rect
                            class="band band-slow"
                            x="-40" y="-40" width="110" height="280"
                            fill="url(#splash-band)"
                        />
                    </mask>
                    <!-- Y el destello corto del final. -->
                    <mask id="splash-flash" maskUnits="userSpaceOnUse" x="0" y="0" width="200" height="200">
                        <rect
                            class="band band-fast"
                            x="-40" y="-40" width="70" height="280"
                            fill="url(#splash-band)"
                        />
                    </mask>
                </defs>

                <!-- El logo, en oro -->
                <g stroke="url(#splash-gold)">
                    <path
                        v-for="(d, i) in PETALS"
                        :key="d"
                        :d="d"
                        class="petal"
                        path-length="1"
                        :style="{ animationDelay: `${i * 95}ms` }"
                    />
                </g>

                <circle
                    v-for="([x, y, r], i) in SPARKS"
                    :key="`s${i}`"
                    :cx="x" :cy="y" :r="r"
                    fill="#F5D9BC"
                    class="spark"
                    :style="{ animationDelay: `${620 + i * 38}ms` }"
                />

                <g class="word">
                    <text class="g" x="100" y="160" fill="url(#splash-gold)">GLOW</text>
                    <text class="s" x="100" y="180" fill="url(#splash-gold)">STUDIOS</text>
                </g>

                <!-- Las dos copias encendidas, cada una recortada por su reflejo -->
                <g v-for="m in ['splash-sheen', 'splash-flash']" :key="m" :mask="`url(#${m})`">
                    <g stroke="#FFF6EA" stroke-width="3">
                        <path
                            v-for="(d, i) in PETALS"
                            :key="d"
                            :d="d"
                            class="petal"
                            path-length="1"
                            :style="{ animationDelay: `${i * 95}ms` }"
                        />
                    </g>
                    <g class="word">
                        <text class="g" x="100" y="160" fill="#FFF6EA">GLOW</text>
                        <text class="s" x="100" y="180" fill="#FFF6EA">STUDIOS</text>
                    </g>
                </g>
            </svg>
        </div>
    </Transition>
</template>

<style scoped>
.splash {
    /* El mismo azul del logo, en los dos temas: es el fondo de la marca, no
       una superficie de la app. */
    background: radial-gradient(120% 90% at 50% 44%, #1b2740 0%, #0e1626 62%);
}

.petal {
    fill: none;
    stroke-width: 2.4px;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
    animation: draw 1350ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes draw {
    to { stroke-dashoffset: 0; }
}

.spark {
    opacity: 0;
    transform-box: fill-box;
    transform-origin: center;
    animation: spark 1600ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes spark {
    0% { opacity: 0; transform: scale(0.15); }
    40% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.3; transform: scale(0.75) translateY(-4px); }
}

.word { opacity: 0; animation: fade 800ms 1710ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes fade { to { opacity: 1; } }

.g {
    font-size: 26px; font-weight: 400; letter-spacing: 0.14em;
    text-anchor: middle; font-family: inherit;
}
.s {
    font-size: 10.5px; font-weight: 500; letter-spacing: 0.42em;
    text-anchor: middle; font-family: inherit;
}

.band {
    opacity: 0;
    /* La inclinación va aquí y no en un atributo transform del SVG: el
       transform del CSS lo habría pisado y el reflejo saldría vertical. */
    transform-box: view-box;
    transform-origin: 100px 100px;
}
.band-slow { animation: sweep 1700ms 420ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.band-fast { animation: sweep 620ms 2490ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes sweep {
    0% { opacity: 0; transform: rotate(18deg) translateX(-150px); }
    12% { opacity: 1; }
    88% { opacity: 1; }
    100% { opacity: 0; transform: rotate(18deg) translateX(150px); }
}

@media (prefers-reduced-motion: reduce) {
    .petal, .spark, .word, .band { animation: none; }
}
</style>
