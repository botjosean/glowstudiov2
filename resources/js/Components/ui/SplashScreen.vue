<script>
/**
 * Si la pantalla de carga ya salió desde que el navegador cargó esta página.
 *
 * **Este bloque existe sólo por esta variable, y no se puede fusionar con el
 * `<script setup>` de abajo.** `<script setup>` compila todo su contenido
 * DENTRO de la función de montaje, así que un `let` escrito allí parece de
 * módulo pero se reinicia en cada montaje — o sea, en cada cambio de pestaña,
 * que es justo lo que hay que distinguir. Un bloque `<script>` normal sí es
 * ámbito de módulo, y `<script setup>` puede leerlo.
 *
 * Ya pasó: el 2026-08-26 se escribió dentro del setup y la corona siguió
 * saliendo en todas las pestañas. Se vio leyendo el bundle compilado, no el
 * fuente — en el fuente parecía correcto.
 *
 * Módulo y no sessionStorage a propósito: las navegaciones de Inertia vuelven
 * a montar el componente pero no recargan el módulo, y una recarga de verdad
 * sí lo tira. Esa muerte es exactamente la señal que hace falta, y sale gratis.
 */
let yaSalioEnEstaCarga = false;
</script>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CROWN_GOLD, CROWN_STROKES } from '../../crown';

/**
 * La pantalla con la que abre la app: la corona se dibuja sola.
 *
 * Elegida por el dueño el 2026-08-17 entre ocho variantes. El 2026-08-22 el
 * loto pasó a ser corona; todo lo demás —el oro, el trazo, los tiempos, el
 * reflejo, el polvo— quedó igual. El trazo va del aro hacia las puntas, que es
 * como se levanta una corona, y un reflejo recorre el logo entero —corona y
 * letras— mientras se dibuja; al final vuelve en un destello corto, cuando ya
 * está todo en pantalla.
 *
 * El dibujo y el oro salen de `crown.js`, el mismo del que tira la marca de la
 * barra: aquí no hay una segunda corona que pueda quedarse atrás.
 *
 * **El reflejo va sobre una copia del logo, no encima del fondo.** Esa copia
 * lleva las mismas animaciones de trazo, así que la luz sólo puede iluminar lo
 * que ya está dibujado: nunca se adelanta al lápiz.
 *
 * **Cuándo se ve.** Esto ya cambió tres veces, así que conviene dejar
 * escrito el porqué de cada vuelta antes de tocarlo una cuarta:
 *
 * 1. Al principio salía **una vez por sesión**. El dueño lo pidió cambiado el
 *    2026-08-17: el logo desaparecía y no volvía nunca.
 * 2. Pasó a salir **en cada navegación de Inertia**, o sea en cada cambio de
 *    pestaña. Lo volvió a pedir así el 2026-08-24, porque seguía sin salir al
 *    pasar de pestañas.
 * 3. Y el 2026-08-26 pidió lo de ahora, con estas palabras: «aparece
 *    demasiado… debería aparecer solamente cuando la gente recargue la página,
 *    o cuando se meta, o cuando vuelva a iniciar sesión. Y también cuando
 *    toque Perfil, y en Configuración. Pero más en ningún lado.»
 *
 * O sea que la regla ya no es «siempre» ni «una vez», son dos cosas a la vez:
 *
 * - **Una carga de verdad** —abrir la app, recargar, volver de iniciar
 *   sesión— siempre la enseña. Se detecta con una variable de MÓDULO, no del
 *   componente: sobrevive a las navegaciones de Inertia, que no recargan nada,
 *   y muere cuando el navegador carga la página de nuevo. Esa muerte es
 *   exactamente la señal que hace falta.
 * - **Y las pantallas que la piden**, que las declara quien usa el componente
 *   con `also-on`. Hoy sólo Ajustes: es la pantalla a la que se entra a
 *   propósito, de vez en cuando y por el engranaje.
 *
 * En Citas, Clientas, Ventas y el propio Perfil ya no sale. Son sitios por los
 * que se pasa todo el día: entra a cobrar veinte veces y tres segundos de
 * corona cada vez son un minuto de su jornada mirando un logo. Perfil estuvo
 * un rato en la lista y el dueño lo quitó el 2026-08-26 — se pasa por ahí más
 * de lo que parece.
 * - **No en la página pública de una profesional** (`disabled`). Ahí llega una
 *   clienta desde un enlace de WhatsApp queriendo ver a Pati, y esa página ya
 *   la saluda con su nombre: dos bienvenidas seguidas son cuatro segundos
 *   antes de lo que vino a hacer.
 * - Nunca para quien pidió menos movimiento (prefers-reduced-motion).
 */
const props = defineProps({
    disabled: { type: Boolean, default: false },
    // Rutas donde sale aunque no sea una carga de verdad. Vacío = solo en
    // cargas de verdad. La política vive en quien usa el componente, no aquí.
    alsoOn: { type: Array, default: () => [] },
});

const page = usePage();
const DURATION = 3200;

const visible = ref(false);
const leaving = ref(false);

let timers = [];

function debeSalir() {
    if (props.disabled) {
        return false;
    }

    // Quien pidió menos movimiento no quiere tres segundos de animación cada
    // vez que abre.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return false;
    }

    // Abrir la app, recargar, o volver de iniciar sesión.
    if (!yaSalioEnEstaCarga) {
        return true;
    }

    const ruta = page.url.split('?')[0];

    return props.alsoOn.some((p) => ruta === p || ruta.startsWith(p + '/'));
}

onMounted(() => {
    const sale = debeSalir();

    // Se marca salga o no: lo que la bandera cuenta es si esta carga del
    // navegador ya montó la pantalla una vez, no si llegó a verse.
    yaSalioEnEstaCarga = true;

    if (!sale) {
        return;
    }

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

// El polvo dorado del logo original: denso a la derecha, suelto abajo a la
// izquierda, y un par sueltos arriba para que no quede todo de un lado.
//
// La corona es más angosta y más alta que el loto, así que unos pocos granos
// quedaron encima del aro o dentro de una perla y hubo que correrlos hacia
// afuera. El reparto —dónde hay mucho y dónde hay poco— es el mismo.
const SPARKS = [
    [152, 30, 0.7], [161, 38, 1.1], [168, 31, 0.6], [157, 47, 1.5], [184, 45, 0.8],
    [164, 56, 1.9], [178, 55, 0.7], [169, 66, 1.2], [181, 68, 1.6], [172, 74, 0.6],
    [175, 79, 1], [186, 82, 0.9], [180, 88, 1.4], [179, 94, 1.2], [190, 73, 0.7],
    [154, 60, 0.8], [184, 60, 0.6], [171, 101, 0.9], [188, 100, 0.7],
    [24, 104, 1.3], [27, 116, 0.9], [22, 99, 1.6], [44, 124, 0.7], [15, 88, 1],
    [26, 92, 0.6], [28, 120, 1.1], [52, 122, 0.8], [9, 105, 0.7],
    [78, 26, 0.7], [120, 22, 0.9], [64, 36, 0.6],
];

// Nada de medir longitudes: pathLength="1" le pide al navegador que trate
// cada trazo como si midiera 1, así el mismo dash de 1 cubre exactamente el
// camino de los nueve, largos o cortos. Medirlos con getTotalLength() obligaría
// a montar el SVG antes de animarlo, y el primer fotograma se vería con la
// corona ya dibujada.
//
// Va escrito en camelCase y no como `path-length`. SVG distingue mayúsculas, y
// con el guion el navegador se limita a ignorar el atributo: el dash de 1 pasa
// a medir una unidad del lienzo de 200, así que la corona no se dibujaba sola
// sino que salía de golpe y punteada, como una línea de puntos.
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
                        <stop offset="0%" :stop-color="CROWN_GOLD[0]" />
                        <stop offset="45%" :stop-color="CROWN_GOLD[1]" />
                        <stop offset="100%" :stop-color="CROWN_GOLD[2]" />
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
                        v-for="(d, i) in CROWN_STROKES"
                        :key="d"
                        :d="d"
                        class="line"
                        pathLength="1"
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
                            v-for="(d, i) in CROWN_STROKES"
                            :key="d"
                            :d="d"
                            class="line"
                            pathLength="1"
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

.line {
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
    .line, .spark, .word, .band { animation: none; }
}
</style>
