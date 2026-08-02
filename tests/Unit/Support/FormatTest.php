<?php

namespace Tests\Unit\Support;

use App\Support\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function phoneFormats(): iterable
    {
        yield 'plain 10 digits' => ['3055550123'];
        yield 'formatted' => ['(305) 555-0123'];
        yield 'dashed' => ['305-555-0123'];
        yield 'e164' => ['+13055550123'];
        yield '11 digits with leading 1' => ['13055550123'];
    }

    #[DataProvider('phoneFormats')]
    public function test_digits_only_normalizes_every_format_to_the_same_ten_digits(string $input): void
    {
        $this->assertSame('3055550123', Format::digitsOnly($input));
    }

    public function test_us_phone_formats_ten_digits(): void
    {
        $this->assertSame('(305) 555-0123', Format::usPhone('3055550123'));
    }

    public function test_us_phone_passes_through_anything_not_exactly_ten_digits(): void
    {
        $this->assertSame('(305) 555-0123', Format::usPhone('(305) 555-0123'));
        $this->assertSame('123', Format::usPhone('123'));
    }
}
