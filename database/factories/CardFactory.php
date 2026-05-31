<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Set;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    protected $model = Card::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => Set::factory(),
            'name' => fake()->unique()->words(3, true),
            'number' => (string) fake()->numberBetween(1, 300),
            'rarity' => fake()->randomElement(['C', 'U', 'R', 'M']),
        ];
    }
}
