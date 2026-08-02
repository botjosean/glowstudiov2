<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $duration = 40;
        $startsAt = CarbonImmutable::now()->addDay()->setTime(10, 0);

        return [
            'provider_id' => Provider::factory(),
            'service_id' => null,
            'client_name' => fake()->name(),
            'client_phone' => fake()->numerify('##########'),
            'service_name' => fake()->words(2, true),
            'duration_minutes' => $duration,
            'price' => fake()->numberBetween(15, 60),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($duration),
            'status' => AppointmentStatus::Pending->value,
        ];
    }

    /**
     * Sets service_id, service_name, duration_minutes and price from the
     * given service TOGETHER, and recomputes ends_at from the (possibly
     * already-set) starts_at — without this, a test could produce an
     * appointment whose ends_at contradicts duration_minutes, and any
     * slot-generation assertion built on it would be lying.
     */
    public function forService(Service $service): static
    {
        return $this->state(function (array $attributes) use ($service) {
            $startsAt = $attributes['starts_at'] instanceof CarbonInterface
                ? $attributes['starts_at']
                : CarbonImmutable::parse($attributes['starts_at']);

            return [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price' => $service->price,
                'ends_at' => $startsAt->addMinutes($service->duration_minutes),
            ];
        });
    }

    /**
     * Sets starts_at (given as local time in $timezone) and recomputes
     * ends_at from the current duration_minutes.
     */
    public function at(CarbonInterface $localStart, string $timezone = 'America/New_York'): static
    {
        return $this->state(function (array $attributes) use ($localStart, $timezone) {
            $startsAt = CarbonImmutable::parse($localStart, $timezone)->utc();

            return [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($attributes['duration_minutes']),
            ];
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AppointmentStatus::Pending->value]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Confirmed->value,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AppointmentStatus::Closed->value]);
    }
}
