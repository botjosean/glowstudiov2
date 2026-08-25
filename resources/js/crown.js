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
 * Segunda corona, no la primera: la del 22-ago era una tiara de tres puntas
 * con perlas. El dueño vio varias opciones y el 24-ago eligió esta —dos hilos
 * que se cruzan como una trenza, con tres piedras encima—. Mismo oro, mismo
 * trazo sin relleno, misma forma de dibujarse sola; cambió solo el dibujo.
 *
 * Los trazos van en un lienzo de 200×200 porque la pantalla de carga necesita
 * sitio debajo para las letras; quien sólo quiere la corona recorta con
 * CROWN_VIEWBOX. Y van en el orden en que se dibujan solos: primero un hilo,
 * y casi encima el otro —95ms de por medio, lo justo para leerse como que se
 * cruzan y no como si uno copiara al otro—, y las tres piedras al final, del
 * centro hacia los lados.
 *
 * El único sitio que NO puede importar esto es `public/icons/icon.svg`: es un
 * archivo suelto que el servidor entrega tal cual, sin pasar por el bundle. Si
 * se cambia un trazo aquí, hay que copiarlo allá —y, sobre todo, volver a
 * exportar los PNG de `public/icons/`, que son el ícono de la pantalla de
 * inicio y no se generan solos.
 */
export const CROWN_STROKES = [
    // Los dos hilos que se trenzan
    'M31 104 C 41 88 51 88 61 104 C 71 120 81 120 91 104 C 101 88 111 88 121 104 C 131 120 141 120 151 104 C 157 96 163 96 169 104',
    'M31 104 C 41 120 51 120 61 104 C 71 88 81 88 91 104 C 101 120 111 120 121 104 C 131 88 141 88 151 104 C 157 96 163 96 169 104',
    // Las tres piedras encima, del centro hacia los lados
    'M101 50 L115 64 L101 78 L87 64 Z',
    'M57 62 L67 74 L57 86 L47 74 Z',
    'M145 62 L155 74 L145 86 L135 74 Z',
];

/**
 * El recorte que deja justo la corona, sin el hueco que la pantalla de carga
 * reserva para las letras. Esta corona es más baja y más ancha que la tiara
 * anterior, así que el recorte es distinto aunque el lienzo de abajo no cambió.
 */
export const CROWN_VIEWBOX = '24 42 152 86';

/**
 * El oro de la marca, de la sombra al brillo y de vuelta. Es el mismo de la
 * pantalla de carga.
 */
export const CROWN_GOLD = ['#B4744A', '#F5D9BC', '#A9683F'];
