<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Por defecto una invitación SIN contestar, que es como nace de verdad:
     * el comando la crea al cerrar la cita y la clienta contesta después, o
     * no contesta nunca. Para una ya contestada está el estado answered().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating' => 0,
            'comment' => null,
            'client_name' => fake()->name(),
            'token' => Review::newToken(),
            'answered_at' => null,
        ];
    }

    public function answered(int $rating = 5, ?string $comment = null): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $rating,
            'comment' => $comment,
            'answered_at' => now(),
        ]);
    }
}
