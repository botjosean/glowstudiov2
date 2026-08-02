<?php

namespace Database\Factories;

use App\Enums\ServiceIcon;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => str($name)->slug(),
            'name' => ucwords($name),
            'icon' => fake()->randomElement(ServiceIcon::cases())->value,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function icon(ServiceIcon $icon): static
    {
        return $this->state(fn (array $attributes) => [
            'icon' => $icon->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
