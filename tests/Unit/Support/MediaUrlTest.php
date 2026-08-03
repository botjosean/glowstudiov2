<?php

namespace Tests\Unit\Support;

use App\Support\MediaUrl;
use Tests\TestCase;

/**
 * Extends the framework TestCase (not plain PHPUnit, unlike FormatTest)
 * because resolve() reaches the Storage facade for the R2-key branch.
 */
class MediaUrlTest extends TestCase
{
    public function test_null_resolves_to_null(): void
    {
        $this->assertNull(MediaUrl::resolve(null));
    }

    public function test_empty_string_resolves_to_null(): void
    {
        $this->assertNull(MediaUrl::resolve(''));
    }

    public function test_an_absolute_url_passes_through_unchanged(): void
    {
        $url = 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c';

        $this->assertSame($url, MediaUrl::resolve($url));
    }

    public function test_an_r2_key_resolves_through_the_configured_disk_url(): void
    {
        config(['filesystems.disks.r2.url' => 'https://media.example.test']);

        $this->assertSame(
            'https://media.example.test/providers/1/avatar/X.webp',
            MediaUrl::resolve('providers/1/avatar/X.webp'),
        );
    }

    public function test_is_managed_key(): void
    {
        $this->assertTrue(MediaUrl::isManagedKey('providers/1/avatar/X.webp'));
        $this->assertFalse(MediaUrl::isManagedKey('https://images.unsplash.com/photo-1.jpg'));
        $this->assertFalse(MediaUrl::isManagedKey('http://example.com/photo.jpg'));
        $this->assertFalse(MediaUrl::isManagedKey(null));
        $this->assertFalse(MediaUrl::isManagedKey(''));
    }
}
