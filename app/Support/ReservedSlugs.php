<?php

namespace App\Support;

class ReservedSlugs
{
    /**
     * Usernames nobody may take, because a provider's public page now lives at
     * the root (`/pati`) instead of behind `/p/`.
     *
     * The catch-all route is registered last, so a real route always wins and
     * these names could never *break* the app — but someone registering as
     * "reservar" would get a profile page they can never reach, which is worse
     * than being told the name is taken.
     *
     * Spelled out rather than derived from Route::getRoutes() on purpose: this
     * list must stay stable across deploys. Deriving it would silently free up
     * a name the day a route is renamed, handing it to whoever registers next
     * while the old links still point at it.
     *
     * @var list<string>
     */
    private const RESERVED = [
        // Every current top-level path and prefix.
        'admin', 'admin-general', 'ajustes', 'auth', 'citas', 'crear-contrasena',
        'crear-cuenta', 'forgot-password', 'horario', 'iniciar-sesion', 'inicio',
        'limpiar', 'olvide-contrasena', 'p', 'perfil', 'privacidad', 'proveedores',
        'reservar', 'reset-password', 'restablecer-contrasena', 'servicios',
        'terminos', 'verificar-correo',
        // Paths the app doesn't serve yet but would be confusing or dangerous to
        // hand out: anything that reads like the platform speaking, not a person.
        'api', 'app', 'ayuda', 'blog', 'checkout', 'contacto', 'cuenta', 'dashboard',
        'faq', 'glowstudio', 'glowstudios', 'help', 'login', 'logout', 'pago', 'pagos',
        'password', 'precios', 'privacy', 'register', 'registro', 'root', 'settings',
        'signin', 'signup', 'soporte', 'support', 'terms', 'www',
    ];

    public static function contains(string $candidate): bool
    {
        return in_array(mb_strtolower(trim($candidate)), self::RESERVED, true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::RESERVED;
    }
}
