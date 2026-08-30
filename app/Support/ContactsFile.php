<?php

namespace App\Support;

/**
 * Saca nombres y teléfonos del archivo de contactos que exporta un teléfono.
 *
 * **Existe porque la vía "bonita" no está en todos lados.** La app usaba solo
 * la API de contactos del navegador (`navigator.contacts`), que únicamente
 * trae Chrome en Android: en iPhone, en Brave y en escritorio el botón de
 * importar directamente desaparecía y no quedaba NINGUNA manera de traer la
 * libreta. Ella lo reportó desde el perfil de Paty: «ya no le da la opción
 * como de importar todos tus contactos».
 *
 * Un archivo .vcf lo exporta cualquier teléfono, y un .csv es lo que da
 * Google Contacts, así que entre los dos se cubre a todo el mundo.
 */
class ContactsFile
{
    /**
     * @return list<array{name: string, phone: string}>
     */
    public static function parse(string $contents): array
    {
        // Los .vcf de iPhone y Android vienen en UTF-8; algunos exportadores
        // viejos meten un BOM que se colaría dentro del primer nombre.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        return str_contains($contents, 'BEGIN:VCARD')
            ? self::fromVcard($contents)
            : self::fromCsv($contents);
    }

    /**
     * @return list<array{name: string, phone: string}>
     */
    private static function fromVcard(string $contents): array
    {
        // vCard corta las líneas largas y las continúa con un espacio o tab
        // al principio de la siguiente. Sin desdoblarlas, un nombre largo
        // llega partido a la mitad.
        $contents = preg_replace("/\r\n[ \t]/", '', $contents) ?? $contents;
        $contents = preg_replace("/\n[ \t]/", '', $contents) ?? $contents;

        $contacts = [];

        foreach (preg_split('/BEGIN:VCARD/i', $contents) ?: [] as $card) {
            if (trim($card) === '') {
                continue;
            }

            $name = self::vcardName($card);
            $phone = self::vcardPhone($card);

            if ($name !== '') {
                $contacts[] = ['name' => $name, 'phone' => $phone];
            }
        }

        return $contacts;
    }

    private static function vcardName(string $card): string
    {
        // FN es el nombre ya armado para mostrar; N viene en piezas
        // (apellido;nombre;...) y se usa solo si no hay FN.
        if (preg_match('/^FN[^:\r\n]*:(.*)$/mi', $card, $m) === 1) {
            return self::clean($m[1]);
        }

        if (preg_match('/^N[^:\r\n]*:(.*)$/mi', $card, $m) === 1) {
            $piezas = array_filter(array_map('trim', explode(';', $m[1])));

            // N va "apellido;nombre": se da vuelta para que quede como se lee.
            if (count($piezas) >= 2) {
                $piezas = [$piezas[1], $piezas[0], ...array_slice($piezas, 2)];
            }

            return self::clean(implode(' ', $piezas));
        }

        return '';
    }

    private static function vcardPhone(string $card): string
    {
        // El primer TEL alcanza: si tiene celular y fijo, el celular casi
        // siempre viene primero, y de todas formas el servidor descarta lo
        // que no sean diez dígitos.
        if (preg_match('/^TEL[^:\r\n]*:(.*)$/mi', $card, $m) === 1) {
            return self::clean($m[1]);
        }

        return '';
    }

    /**
     * @return list<array{name: string, phone: string}>
     */
    private static function fromCsv(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];

        if ($lines === []) {
            return [];
        }

        $header = str_getcsv(array_shift($lines) ?? '', ',', '"', '\\');
        $nameAt = self::columnLike($header, ['name', 'nombre', 'first name', 'display name'], ['type', 'label', 'tipo']);
        // Google Contacts trae "Phone 1 - Type" ANTES de "Phone 1 - Value", y
        // sin descartar la primera se importaba la palabra "Mobile" como
        // teléfono de todo el mundo.
        $phoneAt = self::columnLike(
            $header,
            ['phone', 'tel', 'teléfono', 'telefono', 'mobile', 'celular'],
            ['type', 'label', 'tipo'],
        );

        if ($nameAt === null) {
            return [];
        }

        $contacts = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line, ',', '"', '\\');
            $name = self::clean($row[$nameAt] ?? '');

            if ($name === '') {
                continue;
            }

            $contacts[] = [
                'name' => $name,
                'phone' => $phoneAt === null ? '' : self::clean($row[$phoneAt] ?? ''),
            ];
        }

        return $contacts;
    }

    /**
     * La primera columna cuyo encabezado contenga alguna de esas palabras.
     *
     * Por "contiene" y no por igualdad: Google Contacts exporta la columna
     * del teléfono como "Phone 1 - Value", que nunca coincidiría exacto.
     *
     * @param  list<string>  $header
     * @param  list<string>  $words
     * @param  list<string>  $avoid  encabezados que describen el dato, no el dato
     */
    private static function columnLike(array $header, array $words, array $avoid = []): ?int
    {
        foreach ($header as $i => $column) {
            $column = mb_strtolower(trim($column));

            foreach ($avoid as $word) {
                if (str_contains($column, $word)) {
                    continue 2;
                }
            }

            foreach ($words as $word) {
                if (str_contains($column, $word)) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function clean(string $value): string
    {
        // Los .vcf escapan las comas de un nombre como "\,".
        $value = str_replace(['\\,', '\\;'], [',', ';'], $value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
