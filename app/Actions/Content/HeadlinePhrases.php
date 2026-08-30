<?php

namespace App\Actions\Content;

use App\Enums\BusinessCategory;

/**
 * Las frases que van impresas GRANDES sobre la foto, escritas de antemano
 * para cada oficio.
 *
 * **Por qué existe.** Hasta ahora el titular lo inventaba el modelo en cada
 * post, y salía cosas como «UÑAS DE HOY / GLOW STUDIOS» — el nombre del
 * negocio gastando una línea que el sello ya muestra, y un texto que no dice
 * nada. Ella lo señaló mirando un pack de stickers de belleza que sí funciona:
 * «si tú tuvieras las plantillas correctas todo sería más fácil».
 *
 * Ese pack, y cualquier post de salón que se vea bien, no inventa nada: usa
 * un puñado de frases del oficio, siempre las mismas, en DOS PARTES — una
 * palabra fuerte y una cola corta que la remata. «AGENDA / tu cita»,
 * «ONDAS / de agua», «BOTOX / capilar». De ahí el formato de acá.
 *
 * Elegirlas en PHP y no pedírselas al modelo tiene tres ventajas: no cuesta
 * nada, no puede inventar un servicio que ella no hace, y no puede meter un
 * emoji que la tipografía no dibuja.
 */
class HeadlinePhrases
{
    /**
     * Una frase al azar para ese rubro, ya lista para el motor de titulares.
     *
     * @return list<string>  las dos líneas: la fuerte y su remate
     */
    public static function pick(?BusinessCategory $category): array
    {
        $options = self::forCategory($category);

        return $options[array_rand($options)];
    }

    /**
     * Las de su oficio más las que sirven para cualquiera.
     *
     * Van juntas a propósito: una manicurista también publica «gracias por la
     * confianza», y si solo saliera vocabulario técnico el muro se volvería
     * un catálogo.
     *
     * @return list<list<string>>
     */
    public static function forCategory(?BusinessCategory $category): array
    {
        return [...self::general(), ...self::ofTrade($category)];
    }

    /**
     * Sirven para todos los rubros: agendar, agradecer, mostrar el resultado.
     *
     * @return list<list<string>>
     */
    private static function general(): array
    {
        return [
            ['AGENDA', 'tu cita'],
            ['RESERVA', 'tu lugar'],
            ['CITAS', 'abiertas'],
            ['GRACIAS', 'por la confianza'],
            ['CLIENTA', 'feliz'],
            ['RESULTADO', 'final'],
            ['ANTES', 'y después'],
            ['TRABAJO', 'del día'],
            ['HORARIOS', 'disponibles'],
            ['CUPOS', 'esta semana'],
        ];
    }

    /**
     * El vocabulario propio de cada oficio — lo que ella de verdad escribe
     * en sus historias.
     *
     * @return list<list<string>>
     */
    private static function ofTrade(?BusinessCategory $category): array
    {
        return match ($category) {
            BusinessCategory::Nails => [
                ['ACRILICAS', 'nuevo set'],
                ['SOFT GEL', 'que dura'],
                ['RUBBER', 'base'],
                ['BUILDER GEL', 'natural'],
                ['SEMIPERMANENTE', 'impecable'],
                ['PEDICURA', 'spa'],
                ['MANICURA', 'rusa'],
                ['NAIL ART', 'a mano'],
                ['FRANCESA', 'moderna'],
                ['ENCAPSULADAS', 'con brillo'],
            ],
            BusinessCategory::Hair => [
                ['BALAYAGE', 'a medida'],
                ['ONDAS', 'de agua'],
                ['BOTOX', 'capilar'],
                ['LISO', 'perfecto'],
                ['HIDRATACION', 'profunda'],
                ['MECHAS', 'iluminadas'],
                ['COLOR', 'sin dañar'],
                ['CORTE', 'con estilo'],
                ['KERATINA', 'que dura'],
                ['BABYLIGHTS', 'suaves'],
            ],
            BusinessCategory::Barbershop => [
                ['FADE', 'limpio'],
                ['CORTE', 'a navaja'],
                ['BARBA', 'definida'],
                ['DEGRADADO', 'perfecto'],
                ['PERFILADO', 'de barba'],
                ['TOALLA', 'caliente'],
                ['CLASICO', 'renovado'],
            ],
            BusinessCategory::LashesBrows => [
                ['VOLUMEN', 'extremo'],
                ['EXTENSIONES', 'de pestañas'],
                ['LAMINADO', 'de cejas'],
                ['MIRADA', 'perfecta'],
                ['PELO', 'a pelo'],
                ['CEJAS', 'del día'],
                ['LIFTING', 'de pestañas'],
                ['DEPILACION', 'con hilo'],
            ],
            BusinessCategory::Braids => [
                ['TRENZAS', 'a mano'],
                ['BOX BRAIDS', 'prolijas'],
                ['KNOTLESS', 'sin tensión'],
                ['RAIZ', 'protegida'],
                ['TWIST', 'definidos'],
                ['SEMANAS', 'de duración'],
            ],
            BusinessCategory::Waxing => [
                ['PIEL', 'lisa'],
                ['DEPILACION', 'prolija'],
                ['CERA', 'tibia'],
                ['RESULTADO', 'que dura'],
                ['SIN', 'dolor de más'],
            ],
            BusinessCategory::Makeup => [
                ['MAKEUP', 'para tu día'],
                ['PIEL', 'natural'],
                ['GLAM', 'de noche'],
                ['NOVIA', 'soñada'],
                ['LOOK', 'a prueba de fotos'],
            ],
            BusinessCategory::SpaMassage => [
                ['MASAJE', 'relajante'],
                ['TU', 'momento'],
                ['DESCARGA', 'muscular'],
                ['UNA HORA', 'para vos'],
                ['CUERPO', 'y mente'],
            ],
            BusinessCategory::Aesthetics => [
                ['LIMPIEZA', 'profunda'],
                ['PIEL', 'renovada'],
                ['TRATAMIENTO', 'a medida'],
                ['RESULTADOS', 'reales'],
                ['FACIAL', 'completo'],
            ],
            BusinessCategory::TattooPiercing => [
                ['TATUAJE', 'a medida'],
                ['DISEÑO', 'propio'],
                ['TINTA', 'que dura'],
                ['PIERCING', 'estéril'],
                ['SESION', 'única'],
            ],
            // Sin rubro cargado no se adivina: se queda con las generales,
            // que sirven para cualquier oficio. Adivinar «uñas» sobre una
            // foto de cabello ya pasó una vez y se vio de inmediato.
            default => [],
        };
    }
}
