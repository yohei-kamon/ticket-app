<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->randomElement(['一般', '学生', '子ども']),
            'price' => fake()->numberBetween(0, 10) * 500,
            'capacity' => fake()->numberBetween(10, 200),
            'sales_starts_at' => now()->subDay()->startOfHour(),
            'sales_ends_at' => now()->addWeek()->startOfHour(),
        ];
    }

    /**
     * Indicate that the sales period has not started yet.
     */
    public function notOnSaleYet(): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_starts_at' => now()->addDay(),
            'sales_ends_at' => now()->addWeek(),
        ]);
    }

    /**
     * Indicate that the sales period has ended.
     */
    public function salesEnded(): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_starts_at' => now()->subWeek(),
            'sales_ends_at' => now()->subDay(),
        ]);
    }
}
