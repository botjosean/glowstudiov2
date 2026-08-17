<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            // Same ten-digit normal form appointments and clients use.
            'phone' => fake()->unique()->numerify('305555####'),
            'name' => null,
            'message' => fake()->sentence(),
            'status' => Lead::STATUS_NEW,
            'bot_messages_sent' => 0,
            'first_contact_at' => now(),
            'last_contact_at' => now(),
            'answered_at' => null,
        ];
    }

    /**
     * The receptionist has said everything it is allowed to say.
     */
    public function botDone(): static
    {
        return $this->state(fn (): array => ['bot_messages_sent' => Lead::MAX_BOT_MESSAGES]);
    }

    public function handled(): static
    {
        return $this->state(fn (): array => [
            'status' => Lead::STATUS_HANDLED,
            'answered_at' => now(),
        ]);
    }
}
