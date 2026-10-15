<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::instance(fake()->dateTimeBetween('+1 week', '+3 months'))->startOfHour();

        return [
            'organizer_id' => User::factory()->organizer(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'venue' => fake()->city(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'status' => EventStatus::Draft,
        ];
    }

    /**
     * Indicate that the event is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
        ]);
    }

    /**
     * Indicate that the event was unpublished by an admin.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Unpublished,
        ]);
    }

    /**
     * Indicate that the event has already taken place.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subWeek()->addHours(2),
        ]);
    }
}
