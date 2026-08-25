<?php

namespace Database\Factories;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory(),
            'slug' => fake()->unique()->slug(2),
            'public_name' => $name,
            'bio' => fake()->sentence(12),
            'banner_photo_url' => null,
            'avatar_photo_url' => null,
            'is_mobile' => false,
            'is_available_now' => false,
            'published_at' => null,
            'service_area' => null,
            'address_line' => null,
            'whatsapp_url' => null,
            'instagram_url' => null,
            'tiktok_url' => null,
            'facebook_url' => null,
            'timezone' => 'America/New_York',
            'work_start_minute' => 9 * 60,
            'work_end_minute' => 20 * 60,
            'lunch_start_minute' => 13 * 60,
            'lunch_end_minute' => 14 * 60,
            'buffer_minutes' => 15,
            // Los mismos valores que el default de la base. Sin esto el modelo
            // recién creado los tiene en null (los defaults de la columna no
            // vuelven de la inserción) y botIsOpenNow() daría "siempre abierto"
            // en los tests mientras en producción lee las horas de verdad.
            'bot_start_minute' => 9 * 60,
            'bot_end_minute' => 22 * 60,
            // Una profesional de prueba es una que ya se configuro: sin esto
            // no seria reservable ningun dia y media suite se caeria. El caso
            // contrario tiene su propio estado, scheduleUnsaved().
            'schedule_saved_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
        ]);
    }

    /**
     * Answers WhatsApp as a receptionist: acknowledge, ask, hand over.
     *
     * Not the default on purpose — every existing test describes the agent,
     * and this is meant to change nothing until it is asked for by name.
     */
    public function receptionist(): static
    {
        return $this->state(fn (array $attributes) => [
            'bot_mode' => Provider::BOT_RECEPTIONIST,
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }

    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mobile' => true,
        ]);
    }

    public function availableNow(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available_now' => true,
        ]);
    }

    /**
     * Recien registrada: tiene sus siete dias sembrados por Provider::booted()
     * pero nunca abrio la pantalla de Horario. No es reservable ningun dia y
     * le sale el aviso en el panel.
     */
    public function scheduleUnsaved(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_saved_at' => null,
        ]);
    }

    /**
     * Used by slot-generation tests: full control over the work window.
     */
    public function withSchedule(int $workStart, int $workEnd, int $lunchStart, int $lunchEnd, int $buffer): static
    {
        return $this->state(fn (array $attributes) => [
            'work_start_minute' => $workStart,
            'work_end_minute' => $workEnd,
            'lunch_start_minute' => $lunchStart,
            'lunch_end_minute' => $lunchEnd,
            'buffer_minutes' => $buffer,
        ]);
    }
}
