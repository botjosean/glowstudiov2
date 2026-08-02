<?php

namespace Database\Factories;

use App\Enums\ServiceCategory;
use App\Models\Provider;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
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
            'service_type_id' => ServiceType::factory(),
            'name' => fake()->words(2, true),
            'duration_minutes' => fake()->randomElement(range(5, 180, 5)),
            'price' => fake()->numberBetween(15, 60),
            'category' => fake()->randomElement(ServiceCategory::cases())->value,
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function ofType(ServiceType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type_id' => $type->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
