<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => fake()->name(),
            // Same ten-digit normal form appointments use.
            'phone' => fake()->unique()->numerify('305555####'),
            'email' => null,
            'notes' => null,
            'tags' => [],
        ];
    }

    public function phoneless(): static
    {
        return $this->state(fn (): array => ['phone' => null]);
    }
}
