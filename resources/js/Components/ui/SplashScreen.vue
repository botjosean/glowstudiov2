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
import { CROWN_FILLS, CROWN_GOLD, CROWN_SPARK_PATH, CROWN_STROKES } from '../../crown';

/**
 * La pantalla con la que abre la app: un punto de luz recorre la corona y la
 * va dejando dibujada detrás.
 *
 * Elegida por el dueño el 2026-08-17 entre ocho variantes, con el loto. El
 * 2026-08-22 el loto pasó a ser corona. El 2026-08-26, tras ver dieciséis
 * coronas y una docena larga de animaciones —entre ellas una serie completa
 * llamada "Chispa"—, el dueño pidió exactamente esta: «un punto de luz
 * recorre la corona entera dejando el oro detrás. Las perlas se encienden al
 * pasar», y confirmó que la prefería sobre las variantes más rápidas que
 * también vio. Esos tiempos —no los acelerados— son los de aquí abajo.
 *
 * **Qué cambió y qué no respecto a las dos coronas anteriores.** El oro y el
 * reflejo que recorre el logo mientras se dibuja —el mismo desde el loto—
 * siguen igual. Lo nuevo es el punto de luz explícito (`.trail`, un círculo
 * que viaja por `CROWN_SPARK_PATH` con `offset-path`) y que las perlas y las
 * hojas ya no aparecen todas a la vez: se encienden una por una, en el orden
 * en que la luz las alcanza — ver el orden de CROWN_FILLS en `crown.js`.
 *
 * **El reflejo sigue sin poder adelantarse al lápiz.** Las dos copias
 * blancas —band-slow y band-fast, recortadas por una máscara que se
 * desliza— llevan los mismos trazos, rellenos y demoras que la copia de
 * oro. La luz sólo puede iluminar lo que ya está dibujado.
 *
 * El dibujo y el oro salen de `crown.js`, el mismo del que tira la marca de la
 * barra: aquí no hay una segunda corona que pueda quedarse atrás.
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

// Un poco más larga que las dos coronas anteriores (antes 3200): la luz
// recorre la corona entera antes de que empiecen a encenderse las perlas, así
// que el dibujo completo —arcos, colas, perlas, hojas, letras y el destello
// final— tarda más en terminar de contar su historia.
const DURATION = 3300;

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

/**
 * Cuándo se dibuja cada arco y cada cola, en milisegundos.
 *
 * El mismo orden que CROWN_STROKES: los cuatro arcos primero, de la punta
 * izquierda a la derecha —el camino que sigue `.trail`—, y las dos colas al
 * final, juntas. La demora de cada arco se solapa un poco con la del
 * siguiente a propósito: un arco que termina justo cuando empieza el próximo
 * se ve como una pausa; uno que se solapa se ve como que la luz sigue de
 * largo sin frenar.
 */
const STROKE_TIMING = [
    { delay: 0, duration: 380 },
    { delay: 350, duration: 380 },
    { delay: 700, duration: 380 },
    { delay: 1050, duration: 380 },
    { delay: 1400, duration: 320 },
    { delay: 1400, duration: 320 },
];

/**
 * Cuándo se enciende cada perla y cada hoja, en el mismo orden que
 * CROWN_FILLS: de la punta izquierda a la derecha, siguiendo a la luz. La del
 * centro dura un poco más —380ms contra 320— porque es la que remata la
 * corona; el resto es igual de rápido a los dos lados.
 */
const FILL_TIMING = [
    { delay: 20, duration: 320 },
    { delay: 330, duration: 320 },
    { delay: 470, duration: 260 },
    { delay: 700, duration: 380 },
    { delay: 950, duration: 260 },
    { delay: 1050, duration: 320 },
    { delay: 1400, duration: 320 },
];

// El polvo dorado del logo original: denso a la derecha, suelto abajo a la
// izquierda, y un par sueltos arriba para que no quede todo de un lado.
//
// Sigue valiendo con esta corona sin tocar un número: su silueta en el
// lienzo de 200×200 —una vez colocada con su transform— cae casi exactamente
// donde caía la corona anterior, así que el polvo que ya rodeaba a esa sigue
// rodeando a esta.
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

                <!--
                    La corona y las letras, colocadas en el lienzo de 200×200.
                    Esta corona es proporcionalmente más ancha que la trenza
                    anterior —192 de 200 unidades de CROWN_STROKES, casi de
                    borde a borde—, así que este `translate`+`scale` la
                    reposiciona en vez de que el dibujo cambie. Verificado
                    antes de escribirlo: renderizado y mirado, no solo
                    calculado, para que ni tape las letras ni roce el borde.
                -->
                <g transform="translate(22 39) scale(0.78)">
                    <!-- El logo, en oro -->
                    <g stroke="url(#splash-gold)" fill="none">
                        <path
                            v-for="(d, i) in CROWN_STROKES"
                            :key="d"
                            :d="d"
                            class="line"
                            pathLength="1"
                            :style="{ animationDelay: `${STROKE_TIMING[i].delay}ms`, animationDuration: `${STROKE_TIMING[i].duration}ms` }"
                        />
                    </g>
                    <g fill="url(#splash-gold)">
                        <path
                            v-for="(d, i) in CROWN_FILLS"
                            :key="d"
                            :d="d"
                            class="fillshape"
                            :style="{ animationDelay: `${FILL_TIMING[i].delay}ms`, animationDuration: `${FILL_TIMING[i].duration}ms` }"
                        />
                    </g>

                    <!-- El punto de luz que recorre la corona. Va SIN mask ni
                         copia: es la fuente, no un reflejo, así que siempre
                         se ve entera mientras viaja. -->
                    <circle class="trail" r="2.6" fill="#FFF6EA" />
                </g>

                <circle
                    v-for="([x, y, r], i) in SPARKS"
                    :key="`s${i}`"
                    :cx="x" :cy="y" :r="r"
                    fill="#F5D9BC"
                    class="spark"
                    :style="{ animationDelay: `${1750 + i * 38}ms` }"
                />

                <g class="word">
                    <text class="g" x="100" y="160" fill="url(#splash-gold)">GLOW</text>
                    <text class="s" x="100" y="180" fill="url(#splash-gold)">STUDIOS</text>
                </g>

                <!-- Las dos copias encendidas, cada una recortada por su
                     reflejo. Llevan los mismos trazos, rellenos y demoras que
                     la copia de oro de arriba —nunca pueden mostrar más de lo
                     que ya está dibujado. -->
                <g v-for="m in ['splash-sheen', 'splash-flash']" :key="m" :mask="`url(#${m})`">
                    <g transform="translate(22 39) scale(0.78)">
                        <g stroke="#FFF6EA" stroke-width="3" fill="none">
                            <path
                                v-for="(d, i) in CROWN_STROKES"
                                :key="d"
                                :d="d"
                                class="line"
                                pathLength="1"
                                :style="{ animationDelay: `${STROKE_TIMING[i].delay}ms`, animationDuration: `${STROKE_TIMING[i].duration}ms` }"
                            />
                        </g>
                        <g fill="#FFF6EA">
                            <path
                                v-for="(d, i) in CROWN_FILLS"
                                :key="d"
                                :d="d"
                                class="fillshape"
                                :style="{ animationDelay: `${FILL_TIMING[i].delay}ms`, animationDuration: `${FILL_TIMING[i].duration}ms` }"
                            />
                        </g>
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
    animation-name: draw;
    animation-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
    animation-fill-mode: forwards;
}
@keyframes draw {
    to { stroke-dashoffset: 0; }
}

/* Las perlas y las hojas: sólidas, no trazo. Se encienden con un rebotecito
   —la misma curva con overshoot que ya usaban las chispas de polvo, aquí al
   servicio de "esto acaba de encenderse" en vez de "esto titila". */
.fillshape {
    opacity: 0;
    transform-box: fill-box;
    transform-origin: center;
    animation-name: bloom;
    animation-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1);
    animation-fill-mode: forwards;
}
@keyframes bloom {
    0% { opacity: 0; transform: scale(0.15); }
    100% { opacity: 1; transform: scale(1); }
}

/* El punto de luz. Sigue el mismo camino que ya trazan los cuatro primeros
   `.line` —CROWN_SPARK_PATH es esos mismos cuatro arcos, uno detrás de
   otro—, así que nunca se adelanta ni se atrasa respecto al trazo que va
   dejando. El offset-path va en el propio elemento y no en una clase
   compartida: solo lo usa este círculo. */
.trail {
    offset-path: path('M 12 39 C 20 88 42 88 50 27 C 62 94 88 94 100 9 C 112 94 138 94 150 27 C 158 88 180 88 188 39');
    offset-rotate: 0deg;
    filter: drop-shadow(0 0 3px rgba(255, 246, 234, 0.95)) drop-shadow(0 0 7px rgba(243, 216, 193, 0.7));
    opacity: 0;
    animation: ride 1450ms cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes ride {
    0% { opacity: 0; offset-distance: 0%; }
    6% { opacity: 1; }
    92% { opacity: 1; }
    100% { opacity: 0; offset-distance: 100%; }
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

.word { opacity: 0; animation: fade 700ms 1750ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
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
.band-slow { animation: sweep 2100ms 250ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.band-fast { animation: sweep 650ms 2500ms cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes sweep {
    0% { opacity: 0; transform: rotate(18deg) translateX(-150px); }
    12% { opacity: 1; }
    88% { opacity: 1; }
    100% { opacity: 0; transform: rotate(18deg) translateX(150px); }
}

@media (prefers-reduced-motion: reduce) {
    .line, .fillshape, .trail, .spark, .word, .band { animation: none; }
}
</style>
