/**
 * Un golpecito en el teléfono cuando algo pasa de verdad.
 *
 * La profesional confirma una cita con el teléfono en una mano y las manos
 * ocupadas con una clienta. Una vibración corta le confirma sin obligarla a
 * mirar la pantalla, que es exactamente cuando una confirmación sirve.
 *
 * **Sólo en lo que importa**: confirmar, cancelar, cobrar, fallar. Vibrar en
 * cada toque convierte la señal en ruido y en pocos minutos la profesional
 * apaga la vibración del sistema entero — y ahí se pierde también la de los
 * mensajes.
 *
 * `navigator.vibrate` no existe en Safari de iPhone: allí esto no hace nada, en
 * silencio, que es el comportamiento correcto para un adorno. En Android —los
 * teléfonos de Patricia y Vanessa— funciona sin permisos ni instalación.
 */
function buzz(pattern) {
    // Quien pidió menos movimiento no quiere que el teléfono le tiemble en la
    // mano tampoco.
    if (typeof window === 'undefined'
        || typeof navigator.vibrate !== 'function'
        || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    try {
        navigator.vibrate(pattern);
    } catch {
        // Algunos navegadores lo exponen y lo bloquean por política. No es
        // motivo para romper la acción que el usuario acaba de pedir.
    }
}

export function useHaptics() {
    return {
        /** Algo quedó hecho: cita confirmada, venta registrada. */
        success: () => buzz(18),
        /** Algo se deshizo o se rechazó: cita cancelada, solicitud descartada. */
        warn: () => buzz([12, 60, 12]),
        /** Algo salió mal y hay que mirar la pantalla. */
        error: () => buzz([28, 70, 28]),
    };
}
