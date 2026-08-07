<?php

namespace Tests\Unit\Enums;

use App\Enums\ServiceCategory;
use App\Enums\ServiceIcon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ServiceCategoryTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ServiceCategory}>
     */
    public static function categories(): iterable
    {
        foreach (ServiceCategory::cases() as $category) {
            yield $category->value => [$category];
        }
    }

    /**
     * Both methods are match() expressions with no default arm, so a case
     * added without extending them throws UnhandledMatchError. Iterating
     * every case here means that surfaces as a red test rather than as a 500
     * the first time a provider picks the new category in the panel.
     */
    #[DataProvider('categories')]
    public function test_every_category_resolves_an_icon(ServiceCategory $category): void
    {
        $this->assertInstanceOf(ServiceIcon::class, $category->icon());
    }

    #[DataProvider('categories')]
    public function test_every_category_resolves_a_type_slug_or_null(ServiceCategory $category): void
    {
        $slug = $category->defaultTypeSlug();

        $this->assertTrue($slug === null || $slug !== '');
    }

    /**
     * The five barbershop categories predate the beauty ones and their icons
     * were previously read off the matching ServiceType. Pinning them here
     * keeps a future edit to icon() from silently changing what live services
     * already show on their public profile.
     */
    public function test_barbershop_categories_keep_their_original_icons(): void
    {
        $this->assertSame(ServiceIcon::Scissors, ServiceCategory::Fade->icon());
        $this->assertSame(ServiceIcon::Scissors, ServiceCategory::Classic->icon());
        $this->assertSame(ServiceIcon::Sparkles, ServiceCategory::Beard->icon());
        $this->assertSame(ServiceIcon::Smile, ServiceCategory::Kids->icon());
        $this->assertSame(ServiceIcon::Sparkles, ServiceCategory::Color->icon());
    }
}
