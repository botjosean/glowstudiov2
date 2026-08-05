<?php

namespace Tests\Unit\Kapso;

use App\Support\Kapso\ReplyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplyPolicyTest extends TestCase
{
    /**
     * The property this whole class exists for. The connected WhatsApp numbers
     * are real business lines with live client conversations, so every
     * misconfiguration must resolve to silence.
     *
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('brokenConfigurations')]
    public function test_a_broken_configuration_answers_nobody(array $config): void
    {
        config($config);

        $this->assertFalse($this->policy()->allows('16315551181'));
        $this->assertFalse($this->policy()->allows(null));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function brokenConfigurations(): array
    {
        return [
            'nothing set at all' => [[
                'services.kapso.reply_mode' => null,
                'services.kapso.test_recipients' => null,
            ]],
            'allowlist mode with no list' => [[
                'services.kapso.reply_mode' => 'allowlist',
                'services.kapso.test_recipients' => null,
            ]],
            'allowlist mode with a blank list' => [[
                'services.kapso.reply_mode' => 'allowlist',
                'services.kapso.test_recipients' => '   ',
            ]],
            'a list of separators only' => [[
                'services.kapso.reply_mode' => 'allowlist',
                'services.kapso.test_recipients' => ',,,',
            ]],
            'a misspelled mode' => [[
                'services.kapso.reply_mode' => 'everyoen',
                'services.kapso.test_recipients' => null,
            ]],
            'a mode of the wrong type' => [[
                'services.kapso.reply_mode' => true,
                'services.kapso.test_recipients' => null,
            ]],
        ];
    }

    public function test_it_answers_a_listed_number_and_refuses_the_rest(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '12056455856,16315551181',
        ]);

        $this->assertTrue($this->policy()->allows('12056455856'));
        $this->assertTrue($this->policy()->allows('16315551181'));
        $this->assertFalse($this->policy()->allows('14044518022'));
        $this->assertFalse($this->policy()->allows(null));
    }

    /**
     * Nobody types phone numbers as bare digits, and a list that silently
     * failed to match because of a dash would look exactly like a working one.
     */
    public function test_it_normalises_however_the_numbers_were_written(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '+1 (205) 645-5856 , +1 404-451-8022',
        ]);

        $this->assertTrue($this->policy()->allows('12056455856'));
        $this->assertTrue($this->policy()->allows('14044518022'));
    }

    public function test_everyone_mode_is_an_explicit_opt_in_that_answers_all(): void
    {
        config(['services.kapso.reply_mode' => 'everyone']);

        $this->assertTrue($this->policy()->allows('14044518022'));
        $this->assertTrue($this->policy()->allows('447710173736'));
    }

    public function test_everyone_mode_is_recognised_regardless_of_casing_or_padding(): void
    {
        config(['services.kapso.reply_mode' => '  EVERYONE ']);

        $this->assertTrue($this->policy()->allows('14044518022'));
    }

    /**
     * The description goes into logs, so it must say how the decision was made
     * without writing anyone's phone number to disk.
     */
    public function test_its_description_never_leaks_a_phone_number(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '12056455856,16315551181',
        ]);

        $description = $this->policy()->describe();

        $this->assertSame('allowlist of 2', $description);
        $this->assertStringNotContainsString('12056455856', $description);
    }

    private function policy(): ReplyPolicy
    {
        return new ReplyPolicy;
    }
}
