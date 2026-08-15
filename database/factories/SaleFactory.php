<?php

namespace Database\Factories;

use App\Models\Provider;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'client_id' => null,
            'client_name' => fake()->name(),
            'amount' => fake()->randomFloat(2, 10, 200),
            'tip' => fake()->randomFloat(2, 0, 40),
            'payment_method' => fake()->randomElement(['cash', 'card', 'zelle']),
        ];
    }
}
