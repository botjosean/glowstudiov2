/**
 * La corona de Glow Studios, en trazos. Un solo sitio para el dibujo.
 *
 * Antes vivía copiada en GlowMark y en SplashScreen con una nota de "si se
 * retoca uno, hay que retocar los dos". Al pedir el dueño la corona en todas
 * partes —la barra, la pantalla de carga, el QR, el aviso de instalar— esa nota
 * no iba a aguantar cinco copias: la primera vez que alguien moviera una perla,
 * la app tendría dos coronas distintas. Ahora se retoca aquí y cambia en todos
 * lados a la vez.
 *
 * Tercera corona. La del 22-ago era una tiara de tres puntas con perlas; el
 * 24-ago pasó a ser una trenza de dos hilos con tres piedras. El 25-ago el
 * dueño mandó una foto de referencia —«el definitivo»— y el 26-ago, tras ver
 * dieciséis variantes y varias animaciones, confirmó esta: cinco perlas de
 * alambre sobre cuatro arcos, con dos colas que cuelgan de las perlas de las
 * puntas.
 *
 * **Sin las hojas** (30-ago). Tenía además dos hojas colgando bajo la segunda
 * y la cuarta perla, y a tamaño real no se leían como hojas sino como dos
 * rayas rectas que rompían la curva. Ella lo vio así: «esas dos rayas que van
 * hacia abajo rectas, quitalas de todo el logo, que quede bien simple». Todo
 * lo demás es curvo, así que dos aristas rectas desentonaban.
 *
 * **Por qué ahora son DOS listas y no una.** Las dos coronas anteriores eran
 * puro trazo —`fill: none` en el grupo entero, en los tres consumidores—
 * porque sus "piedras" eran rombos que se leían bien como contorno. Esta
 * corona tiene perlas de verdad: bolitas sólidas, no aros. Dibujarlas solo con
 * trazo las deja huecas, que no es lo que hay en la foto. Así que el trazo
 * (los arcos y las colas, que sí son de alambre) y el relleno (las perlas y
 * las dos hojas, que son sólidos) viven en listas separadas, y cada
 * consumidor pinta las dos: una con `stroke`, otra con `fill`.
 *
 * **Las coordenadas son las mismas en todos lados** — no hay una copia para
 * la pantalla de carga y otra para el ícono. Lo que cambia por sitio es el
 * `transform` que las coloca: esta corona es proporcionalmente más ancha que
 * la trenza anterior (192 de 200 unidades, casi de borde a borde), así que
 * cada lienzo la reposiciona con su propio `translate`+`scale` en vez de que
 * el dibujo mismo cambie. Los tres transforms están verificados de antemano
 * —renderizados y mirados, no solo calculados— para que ni la pantalla de
 * carga tape el texto ni el ícono roce las esquinas redondeadas.
 *
 * El orden de CROWN_STROKES y CROWN_FILLS es también el orden en que se
 * dibujan solos: un punto de luz recorre los cuatro arcos de izquierda a
 * derecha —empieza en la perla de la punta, pasa por el centro, termina en la
 * punta opuesta—, y encima las perlas se van encendiendo a su paso, del
 * centro hacia los lados según llegue. Las colas se trazan al final, juntas,
 * como un cierre.
 *
 * El único sitio que NO puede importar esto es `public/icons/icon.svg`: es un
 * archivo suelto que el servidor entrega tal cual, sin pasar por el bundle. Si
 * se cambia un trazo aquí, hay que copiarlo allá —y, sobre todo, volver a
 * exportar los PNG de `public/icons/`, que son el ícono de la pantalla de
 * inicio y no se generan solos.
 */
export const CROWN_STROKES = [
    // Los cuatro arcos, de la punta izquierda a la punta derecha —el camino
    // que recorre la luz.
    'M 12 39 C 20 88 42 88 50 27',
    'M 50 27 C 62 94 88 94 100 9',
    'M 100 9 C 112 94 138 94 150 27',
    'M 150 27 C 158 88 180 88 188 39',
    // Las dos colas, al final y juntas.
    'M 12 39 C 14 56 24 72 35 88',
    'M 188 39 C 186 56 176 72 165 88',
];

/**
 * Las cinco perlas, sólidas — no son trazo. Un círculo se escribe como dos
 * semicírculos (`A r r 0 1 0 …` dos veces) porque este archivo solo exporta
 * strings de `d`, para que cada consumidor los pinte igual que los de arriba,
 * con un `v-for` y nada más.
 *
 * **De izquierda a derecha**, en el mismo orden en que el punto de luz las
 * va a encontrar en su camino —CROWN_SPARK_PATH recorre la corona en ese
 * sentido—: primero la perla de la punta izquierda, después la de en medio a
 * ese lado, la del centro cuando el punto pasa por arriba, y así en espejo
 * hacia la derecha. No es "del centro hacia los lados": ese orden tenía
 * sentido con las tres piedras de la corona anterior, que se dibujaban todas
 * a la vez; esta se enciende sola, siguiendo a la luz.
 */
export const CROWN_FILLS = [
    'M 6 39 A 6 6 0 1 0 18 39 A 6 6 0 1 0 6 39 Z',           // perla, punta izquierda
    'M 43.5 27 A 6.5 6.5 0 1 0 56.5 27 A 6.5 6.5 0 1 0 43.5 27 Z', // perla, medio izquierda
    'M 92 9 A 8 8 0 1 0 108 9 A 8 8 0 1 0 92 9 Z',            // perla del centro
    'M 143.5 27 A 6.5 6.5 0 1 0 156.5 27 A 6.5 6.5 0 1 0 143.5 27 Z', // perla, medio derecha
    'M 182 39 A 6 6 0 1 0 194 39 A 6 6 0 1 0 182 39 Z',       // perla, punta derecha
];

/**
 * El camino que recorre el punto de luz en la pantalla de carga: los cuatro
 * arcos de CROWN_STROKES —los primeros cuatro de la lista, sin las colas—,
 * uno detrás de otro, como una sola curva continua. Va escrito a mano y no
 * derivado de CROWN_STROKES en tiempo de ejecución: un `C` no necesita repetir
 * el punto donde empieza, así que concatenar los cuatro es tan simple como
 * pegar los `d` sin el `M` de más —pero hacerlo con código (cortar el primer
 * `M` de cada uno) deja un `L` de largo cero en cada unión, que no rompe nada
 * visible pero es ruido para quien lo lea después. Más simple escribirlo
 * literal, ya verificado: es este mismo camino el que usan los prototipos
 * animados que aprobó el dueño.
 */
export const CROWN_SPARK_PATH = 'M 12 39 C 20 88 42 88 50 27 C 62 94 88 94 100 9 C 112 94 138 94 150 27 C 158 88 180 88 188 39';

/**
 * El recorte que deja justo la corona, para quien solo quiere el icono
 * —GlowMark, la marca de la barra—. Con margen de sobra para las puntas
 * redondas del trazo más grueso que usa esa marca (5.5, más grueso que el de
 * la pantalla de carga).
 */
export const CROWN_VIEWBOX = '2 -3 196 97';

/**
 * El oro de la marca, de la sombra al brillo y de vuelta. No cambió con esta
 * corona —solo cambió el dibujo, ver arriba—, así que sigue siendo el mismo
 * que ya usan la barra, la pantalla de carga y el ícono.
 */
export const CROWN_GOLD = ['#B4744A', '#F5D9BC', '#A9683F'];
