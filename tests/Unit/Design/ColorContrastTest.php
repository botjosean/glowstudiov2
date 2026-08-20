<?php

namespace Tests\Unit\Design;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The text ramp is a promise, not a palette: every level has to stay readable
 * on every surface it can land on. This test reads app.css itself — the same
 * file the browser reads — so a token edited in a hurry cannot quietly drop
 * below the floor. It caught the pair that shipped faint at 2.31:1.
 *
 * Only the --text-* ramp is asserted. The semantic colors are not all text:
 * --gold and --green-text also draw borders, dots and icons, where the 3:1
 * non-text threshold applies. Their text-safe siblings (--gold-text,
 * --green-text-strong) are the ones a label should use.
 */
class ColorContrastTest extends TestCase
{
    /** WCAG 2.1 AA, normal-sized text. */
    private const FLOOR = 4.5;

    private const TEXT_TOKENS = [
        '--text-strong',
        '--text-heading',
        '--text-body',
        '--text-mute',
        '--text-faint',
    ];

    /** Both surfaces a text token can sit on; --surface-mute is the harsher one. */
    private const SURFACES = ['--surface', '--surface-mute'];

    public static function themes(): array
    {
        return [
            'light' => [':root'],
            'dark' => ["[data-theme='dark']"],
        ];
    }

    #[DataProvider('themes')]
    public function test_every_text_level_is_readable_on_every_surface(string $selector): void
    {
        $tokens = $this->tokensFrom($selector);

        foreach (self::TEXT_TOKENS as $token) {
            foreach (self::SURFACES as $surface) {
                $ratio = $this->contrast($tokens[$token], $tokens[$surface]);

                $this->assertGreaterThanOrEqual(
                    self::FLOOR,
                    $ratio,
                    sprintf(
                        '%s (%s) on %s (%s) is %.2f:1 in the %s theme — below the %.1f:1 floor.',
                        $token, $tokens[$token], $surface, $tokens[$surface],
                        $ratio, $selector, self::FLOOR
                    )
                );
            }
        }
    }

    /**
     * The ramp also has to read as a ramp: two levels that measure the same
     * are one level wearing two names, and the hierarchy stops meaning anything.
     */
    #[DataProvider('themes')]
    public function test_the_ramp_keeps_its_steps_apart(string $selector): void
    {
        $tokens = $this->tokensFrom($selector);
        $previous = null;

        foreach (self::TEXT_TOKENS as $token) {
            $luminance = $this->luminance($tokens[$token]);

            if ($previous !== null) {
                $this->assertNotEqualsWithDelta(
                    $previous, $luminance, 0.005,
                    "{$token} is indistinguishable from the level above it in the {$selector} theme."
                );
            }

            $previous = $luminance;
        }
    }

    /** @return array<string,string> */
    private function tokensFrom(string $selector): array
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $start = strpos($css, $selector.' {');
        $this->assertNotFalse($start, "No {$selector} block in app.css.");

        $block = substr($css, $start, strpos($css, '}', $start) - $start);

        preg_match_all('/(--[a-z-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $block, $matches, PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = $match[2];
        }

        foreach ([...self::TEXT_TOKENS, ...self::SURFACES] as $required) {
            $this->assertArrayHasKey($required, $tokens, "{$required} is missing from {$selector}.");
        }

        return $tokens;
    }

    private function contrast(string $a, string $b): float
    {
        $first = $this->luminance($a);
        $second = $this->luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    /** WCAG relative luminance. */
    private function luminance(string $hex): float
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        $channel = static function (int $value): float {
            $srgb = $value / 255;

            return $srgb <= 0.03928
                ? $srgb / 12.92
                : (($srgb + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
