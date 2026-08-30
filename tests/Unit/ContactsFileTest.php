<?php

namespace Tests\Unit;

use App\Support\ContactsFile;
use PHPUnit\Framework\TestCase;

/**
 * El archivo de contactos que exporta un teléfono es la única vía de importar
 * que funciona en todos lados: la API de contactos del navegador solo la trae
 * Chrome de Android. Ver ContactsFile.
 */
class ContactsFileTest extends TestCase
{
    public function test_it_reads_a_vcard_from_a_phone(): void
    {
        $vcf = "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:María López\r\nTEL;TYPE=CELL:+1 (470) 886-7197\r\nEND:VCARD\r\n"
            ."BEGIN:VCARD\r\nVERSION:3.0\r\nFN:Ana Pérez\r\nTEL:4705551234\r\nEND:VCARD\r\n";

        $this->assertSame([
            ['name' => 'María López', 'phone' => '+1 (470) 886-7197'],
            ['name' => 'Ana Pérez', 'phone' => '4705551234'],
        ], ContactsFile::parse($vcf));
    }

    public function test_it_falls_back_to_the_structured_name_and_flips_it(): void
    {
        // N viene "apellido;nombre": sin darlo vuelta quedaría "López María".
        $vcf = "BEGIN:VCARD\nVERSION:2.1\nN:López;María;;;\nTEL:4705551234\nEND:VCARD\n";

        $this->assertSame(
            [['name' => 'María López', 'phone' => '4705551234']],
            ContactsFile::parse($vcf),
        );
    }

    public function test_it_unfolds_the_long_lines_a_vcard_wraps(): void
    {
        // vCard corta a los 75 caracteres y continúa con un espacio.
        $vcf = "BEGIN:VCARD\r\nFN:María Fernanda\r\n  López Rodríguez\r\nTEL:4705551234\r\nEND:VCARD\r\n";

        $this->assertSame(
            'María Fernanda López Rodríguez',
            ContactsFile::parse($vcf)[0]['name'],
        );
    }

    public function test_it_reads_a_google_contacts_csv(): void
    {
        // La columna del teléfono se llama "Phone 1 - Value": por eso se
        // busca por "contiene" y no por igualdad.
        $csv = "Name,Given Name,Phone 1 - Type,Phone 1 - Value\n"
            ."María López,María,Mobile,(470) 886-7197\n"
            ."Ana Pérez,Ana,Mobile,4705551234\n";

        $this->assertSame([
            ['name' => 'María López', 'phone' => '(470) 886-7197'],
            ['name' => 'Ana Pérez', 'phone' => '4705551234'],
        ], ContactsFile::parse($csv));
    }

    public function test_a_contact_with_no_phone_still_comes_through(): void
    {
        // Lo descarta después el servidor, pero el parser no tiene por qué
        // decidir eso — y un vCard sin TEL no debe romper la lectura.
        $vcf = "BEGIN:VCARD\nFN:Sin Teléfono\nEND:VCARD\n";

        $this->assertSame(
            [['name' => 'Sin Teléfono', 'phone' => '']],
            ContactsFile::parse($vcf),
        );
    }

    public function test_something_that_is_not_a_contacts_file_reads_as_nothing(): void
    {
        $this->assertSame([], ContactsFile::parse('hola, esto es un texto cualquiera'));
    }
}
