<?php

namespace Tests\Unit\Kapso;

use App\Support\Kapso\OptOutRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OptOutRegistryTest extends TestCase
{
    #[DataProvider('optOutMessages')]
    public function test_it_recognises_an_opt_out_request(string $message): void
    {
        $this->assertTrue(OptOutRegistry::isOptOutRequest($message));
    }

    /**
     * @return list<array{string}>
     */
    public static function optOutMessages(): array
    {
        return [
            ['STOP'],
            ['stop'],
            ['Stop.'],
            ['  stop  '],
            ['BAJA'],
            ['No molestar'],
            ['no me escriban'],
            ['unsubscribe'],
            ['Cancelar suscripción'], // accents are normalised away
            ['Deja de escribirme'],
        ];
    }

    /**
     * The dangerous direction. "cancelar" appears in both an opt-out and a
     * perfectly normal booking request, and treating a client who wants to move
     * an appointment as someone who never wants to hear from the salon again
     * would silently cut her off.
     */
    #[DataProvider('ordinaryMessages')]
    public function test_it_does_not_mistake_an_ordinary_message_for_an_opt_out(string $message): void
    {
        $this->assertFalse(OptOutRegistry::isOptOutRequest($message));
    }

    /**
     * @return list<array{string}>
     */
    public static function ordinaryMessages(): array
    {
        return [
            ['quiero cancelar mi cita'],
            ['Necesito cancelar la cita del viernes'],
            ['puedo cancelar?'],
            ['stop me está saliendo un error'],
            ['no me escriban tan temprano por favor, mejor en la tarde'],
            ['hola'],
            ['cuánto cuesta el balayage'],
            [''],
        ];
    }

    public function test_it_recognises_coming_back(): void
    {
        $this->assertTrue(OptOutRegistry::isOptInRequest('START'));
        $this->assertTrue(OptOutRegistry::isOptInRequest('alta'));
        $this->assertFalse(OptOutRegistry::isOptInRequest('start the appointment please'));
    }
}
