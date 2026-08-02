<?php

namespace Database\Seeders;

use App\Enums\ServiceIcon;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    /**
     * The global service catalog. Positions 1-4 are the types that carry
     * services in ProviderSeeder and reproduce Home's mock numbers exactly;
     * 5-12 pad stats.services to 12 (some carry services too, the rest are
     * just catalog depth).
     *
     * @var list<array{slug: string, name: string, icon: ServiceIcon}>
     */
    private const TYPES = [
        ['slug' => 'vip-haircut-beard', 'name' => 'VIP Haircut + Beard', 'icon' => ServiceIcon::Scissors],
        ['slug' => 'haircut', 'name' => 'Haircut', 'icon' => ServiceIcon::Scissors],
        ['slug' => 'beard-grooming', 'name' => 'Beard grooming', 'icon' => ServiceIcon::Sparkles],
        ['slug' => 'kids-haircut', 'name' => 'Kids haircut', 'icon' => ServiceIcon::Smile],
        ['slug' => 'hair-coloring', 'name' => 'Hair Coloring', 'icon' => ServiceIcon::Sparkles],
        ['slug' => 'hot-towel-shave', 'name' => 'Hot Towel Shave', 'icon' => ServiceIcon::Scissors],
        ['slug' => 'line-up', 'name' => 'Line Up', 'icon' => ServiceIcon::Scissors],
        ['slug' => 'hair-treatment', 'name' => 'Hair Treatment', 'icon' => ServiceIcon::Sparkles],
        ['slug' => 'full-grooming-package', 'name' => 'Full Grooming Package', 'icon' => ServiceIcon::Smile],
        ['slug' => 'beard-trim', 'name' => 'Beard Trim', 'icon' => ServiceIcon::Scissors],
        ['slug' => 'skin-fade', 'name' => 'Skin Fade', 'icon' => ServiceIcon::Scissors],
        ['slug' => 'mustache-trim', 'name' => 'Mustache Trim', 'icon' => ServiceIcon::Smile],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::TYPES as $position => $type) {
            ServiceType::create([
                'slug' => $type['slug'],
                'name' => $type['name'],
                'icon' => $type['icon']->value,
                'position' => $position + 1,
                'is_active' => true,
            ]);
        }
    }
}
