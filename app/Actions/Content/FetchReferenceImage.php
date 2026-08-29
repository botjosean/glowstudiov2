<?php

namespace App\Actions\Content;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Trae una imagen desde un enlace que ella pegó, para guardarla como
 * referencia sin tener que descargarla al teléfono primero.
 *
 * **Esto es una petición que hace el SERVIDOR a una dirección que escribe
 * quien sea, así que el peligro no es la imagen: es a dónde apunta el
 * enlace.** Sin candado, alguien pega `http://169.254.169.254/...` y el
 * servidor le trae las credenciales de la nube, o `http://localhost:5432`
 * y le confirma qué corre por dentro. Por eso acá se resuelve el nombre
 * ANTES de pedir nada y se rechaza toda dirección que no sea pública.
 */
class FetchReferenceImage
{
    /** 20 MB: una foto de web nunca pesa más, y acota lo que se descarga. */
    private const MAX_BYTES = 20 * 1024 * 1024;

    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * @return array{ok: true, body: string, mime: string}|array{ok: false, error: string}
     */
    public function handle(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return ['ok' => false, 'error' => 'admin.contentLinkInvalid'];
        }

        // Solo http/https: `file://` leería del disco del servidor y
        // `gopher://` es un clásico para hablarle a otros servicios.
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'admin.contentLinkInvalid'];
        }

        if (! $this->resolvesToPublicAddress($parts['host'])) {
            return ['ok' => false, 'error' => 'admin.contentLinkBlocked'];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'image/*'])
                // Sin seguir redirecciones: una redirección puede llevar a una
                // dirección interna después de que el nombre pasó el control.
                ->withoutRedirecting()
                ->get($url);
        } catch (ConnectionException) {
            return ['ok' => false, 'error' => 'admin.contentLinkUnreachable'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'error' => 'admin.contentLinkUnreachable'];
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! in_array($mime, self::ALLOWED, true)) {
            return ['ok' => false, 'error' => 'admin.contentLinkNotImage'];
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'admin.contentLinkTooBig'];
        }

        // La cabecera la escribe el otro servidor: lo que manda es el archivo.
        if (@getimagesizefromstring($body) === false) {
            return ['ok' => false, 'error' => 'admin.contentLinkNotImage'];
        }

        return ['ok' => true, 'body' => $body, 'mime' => $mime];
    }

    /**
     * Si ese nombre apunta a una dirección pública de internet.
     *
     * Se comprueban TODAS las direcciones que devuelve el nombre, no solo la
     * primera: un nombre puede resolver a una pública y a una privada a la
     * vez, y con quedarse en la primera el candado se salta solo.
     */
    private function resolvesToPublicAddress(string $host): bool
    {
        // Los corchetes de una IPv6 en una URL: [::1] llega así.
        $bare = trim($host, '[]');

        // Si ya es un número, se decide acá y no se consulta el DNS. Además de
        // ser lo correcto, evita una espera de diez segundos por cada intento:
        // preguntarle al DNS por "127.0.0.1" no responde, se agota el tiempo.
        if (filter_var($bare, FILTER_VALIDATE_IP) !== false) {
            return filter_var($bare, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        // 'localhost' no es un número pero tampoco llega al DNS público.
        if (strcasecmp($bare, 'localhost') === 0 || str_ends_with(strtolower($bare), '.localhost')) {
            return false;
        }

        $records = @dns_get_record($bare, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            // Un nombre que no resuelve no se intenta.
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($ip === null) {
                continue;
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }
}
