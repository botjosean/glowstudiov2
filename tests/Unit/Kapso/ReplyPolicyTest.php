<?php

namespace Tests\Unit\Kapso;

use App\Support\Kapso\ReplyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReplyPolicyTest extends TestCase
{
    private const NUMBER = '868324373028256';

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

        $this->assertFalse($this->policy()->allows('16315551181', self::NUMBER, null));
        $this->assertFalse($this->policy()->allows(null, self::NUMBER, null));
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

        $this->assertTrue($this->policy()->allows('12056455856', self::NUMBER, null));
        $this->assertTrue($this->policy()->allows('16315551181', self::NUMBER, null));
        $this->assertFalse($this->policy()->allows('14044518022', self::NUMBER, null));
        $this->assertFalse($this->policy()->allows(null, self::NUMBER, null));
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

        $this->assertTrue($this->policy()->allows('12056455856', self::NUMBER, null));
        $this->assertTrue($this->policy()->allows('14044518022', self::NUMBER, null));
    }

    public function test_everyone_mode_is_an_explicit_opt_in_that_answers_all(): void
    {
        config(['services.kapso.reply_mode' => 'everyone']);

        $this->assertTrue($this->policy()->allows('14044518022', self::NUMBER, null));
        $this->assertTrue($this->policy()->allows('447710173736', self::NUMBER, null));
    }

    public function test_everyone_mode_is_recognised_regardless_of_casing_or_padding(): void
    {
        config(['services.kapso.reply_mode' => '  EVERYONE ']);

        $this->assertTrue($this->policy()->allows('14044518022', self::NUMBER, null));
    }

    /**
     * Vanessa's situation: her number still carries her own personal contacts.
     * Anybody already saved on that phone is never answered — only a stranger
     * writing for the first time is.
     */
    public function test_a_saved_contact_is_never_answered_on_the_guarded_number(): void
    {
        config([
            'services.kapso.reply_mode' => 'everyone',
            'services.kapso.personal_phone_number_id' => self::NUMBER,
        ]);

        $this->assertFalse($this->policy()->allows('14045551234', self::NUMBER, 'Su Prima'));
        $this->assertTrue($this->policy()->allows('14045555678', self::NUMBER, null));
    }

    /**
     * A receptionist sends one short "got your message, she'll reply" and
     * stops, which is welcome even from somebody's own mother — the owner
     * asked for exactly this on 2026-08-16. The guard exists because an
     * *agent* chats, so it is scoped to agent mode rather than deleted.
     */
    public function test_the_guard_does_not_apply_to_a_number_answering_as_a_receptionist(): void
    {
        config([
            'services.kapso.reply_mode' => 'everyone',
            'services.kapso.personal_phone_number_id' => self::NUMBER,
        ]);

        $this->assertTrue($this->policy()->allows('14045551234', self::NUMBER, 'Su Prima', guardPersonalContacts: false));
    }

    public function test_the_reason_stops_blaming_the_personal_guard_when_it_did_not_run(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.personal_phone_number_id' => self::NUMBER,
        ]);

        $this->assertSame(
            'not in the allowlist',
            $this->policy()->refusalReason('14045551234', self::NUMBER, 'Su Prima', guardPersonalContacts: false),
        );
    }

    /**
     * The guard is scoped to one number. Patricia's number must behave exactly
     * as before even though the config has a guarded number configured for
     * Vanessa's.
     */
    public function test_the_guard_never_applies_to_a_different_number(): void
    {
        config([
            'services.kapso.reply_mode' => 'everyone',
            'services.kapso.personal_phone_number_id' => '1208335042365152',
        ]);

        $this->assertTrue($this->policy()->allows('14045551234', self::NUMBER, 'Un Contacto Guardado'));
    }

    public function test_the_guard_is_inert_when_unset(): void
    {
        config([
            'services.kapso.reply_mode' => 'everyone',
            'services.kapso.personal_phone_number_id' => null,
        ]);

        $this->assertTrue($this->policy()->allows('14045551234', self::NUMBER, 'Alguien Guardado'));
    }

    /**
     * The trap this whole guard exists to avoid: Kapso does not send null
     * when nobody saved the sender, it echoes the sender's own phone number
     * back as the "name" (confirmed against real conversations on Patricia's
     * number). A naive "name is present" check would have silenced the
     * assistant for every stranger on Vanessa's number, including brand new
     * clients — the exact opposite of the point.
     */
    public function test_kapsos_fallback_name_of_the_bare_phone_number_is_not_treated_as_saved(): void
    {
        config([
            'services.kapso.reply_mode' => 'everyone',
            'services.kapso.personal_phone_number_id' => self::NUMBER,
        ]);

        $this->assertTrue($this->policy()->allows('14045551234', self::NUMBER, '14045551234'));
        // Same, written the way Kapso writes an international number.
        $this->assertTrue($this->policy()->allows('447710173736', self::NUMBER, '447710173736'));
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

    /**
     * The reason has one job the count could not do: telling "the guard is
     * working" apart from "the guard has gone blind".
     *
     * Both silence the number. Only the second is an outage — if Kapso ever
     * changes the shape of its fallback name, every stranger starts reading as
     * a saved contact and the assistant stops answering that number entirely,
     * with no error anywhere. Whoever greps the log after a quiet day needs
     * those two to look different.
     */
    public function test_the_logged_reason_separates_a_real_contact_from_a_name_that_does_not_match(): void
    {
        config([
            'services.kapso.reply_mode' => 'everyone',
            'services.kapso.personal_phone_number_id' => self::NUMBER,
        ]);

        $this->assertSame(
            'saved under a name on the number shared with personal use',
            $this->policy()->refusalReason('14045551234', self::NUMBER, 'Alguien Guardado'),
        );

        $this->assertSame(
            'name with digits that do not match the sender on the guarded number',
            $this->policy()->refusalReason('14045551234', self::NUMBER, '+1 404 555 9999'),
        );

        $this->assertSame(
            'guarded number and no sender phone to compare the name against',
            $this->policy()->refusalReason(null, self::NUMBER, '14045551234'),
        );
    }

    public function test_the_logged_reason_never_leaks_a_phone_number(): void
    {
        config([
            'services.kapso.reply_mode' => 'allowlist',
            'services.kapso.test_recipients' => '12056455856',
        ]);

        $reason = $this->policy()->refusalReason('14045551234', self::NUMBER, null);

        $this->assertSame('not in the allowlist', $reason);
        $this->assertStringNotContainsString('14045551234', $reason);
    }

    private function policy(): ReplyPolicy
    {
        return new ReplyPolicy;
    }
}
