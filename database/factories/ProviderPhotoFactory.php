<?php

namespace Database\Factories;

use App\Models\Provider;
use App\Models\ProviderPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderPhoto>
 */
class ProviderPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'url' => fake()->imageUrl(400, 400),
            'position' => 0,
        ];
    }
}
