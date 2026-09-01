<?php

namespace App\Actions\Content;

/**
 * Qué frases del pack puede estampar el sistema solo, y cuáles no.
 *
 * **El problema, en sus palabras.** «Tú no sabes cuándo la foto está
 * terminada o cuándo está empezando el proceso. Me mandas la foto y dices
 * que quedó bien, pero es incoherente: al otro le pones proceso y de
 * repente no, ya eso es terminado.»
 *
 * Tiene razón, y no es un problema de afinar el modelo. Hay frases que
 * afirman un MOMENTO del trabajo —«antes», «después», «proceso»,
 * «resultado final»— y ese dato no está en la foto: dos fotos de unas uñas
 * terminadas y a medio hacer se parecen muchísimo, y la que sabe cuál es
 * cuál es ella. Adivinarlo y errarle produce un post que se contradice, que
 * es peor que un post sin etiqueta.
 *
 * **Lo que sí es seguro** es todo lo que no afirma un momento: el nombre de
 * la técnica («Balayage», «Acrílicas», «microblading») es verdad siempre que
 * la técnica sea la correcta, y las llamadas a agendar («agenda abierta»,
 * «reserva tu cita») son verdad siempre. Esas son la mayoría.
 *
 * Las de momento no se borran: quedan para que las ponga ELLA a mano en el
 * editor, que es donde ese dato sí existe.
 */
class PhraseSafety
{
    /**
     * Las que afirman un momento del trabajo. Solo a mano.
     *
     * @var list<string>
     */
    private const CLAIMS_STATE = [
        'antes',
        'despues',
        'antes-y-despues',
        'antes-antes',
        'despues-despues',
        'proceso',
        'resultado',
        'resultado-final',
        'estamos-atendiendo',
        // "referencia" dice que la foto es un ejemplo tomado de otro lado,
        // no un trabajo de ella. Estamparlo sobre su propio trabajo lo
        // regala como ajeno.
        'referencia',
    ];

    /**
     * Rotas o mal escritas en el pack original. No las estampa ni el
     * sistema ni ella.
     *
     * @var list<string>
     */
    private const BROKEN = [
        // "Traicional" por "Tradicional".
        'traicional',
        // El texto repetido cuatro veces por un error de armado.
        'agenda-tu-cita-agenda-tu-cita-agenda-tu-cita-agenda-tu-cita',
        // "agenda abierto" en masculino.
        'agenda-abierto',
        'polygely',
    ];

    /** ¿Puede el sistema estampar esto solo? */
    public static function autoSafe(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            // Sin texto leído no se sabe qué dice: no se arriesga.
            return false;
        }

        return ! in_array($slug, self::CLAIMS_STATE, true)
            && ! in_array($slug, self::BROKEN, true);
    }

    /** ¿Se le puede ofrecer a ella en el editor? */
    public static function usable(?string $slug): bool
    {
        return $slug === null || ! in_array($slug, self::BROKEN, true);
    }

    /**
     * Las que solo puede poner ella, para que el editor las agrupe aparte.
     *
     * @return list<string>
     */
    public static function manualOnly(): array
    {
        return self::CLAIMS_STATE;
    }

    /**
     * @return list<string>
     */
    public static function broken(): array
    {
        return self::BROKEN;
    }
}
