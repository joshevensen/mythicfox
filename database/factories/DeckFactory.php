<?php

namespace Database\Factories;

use App\Models\Deck;
use App\Models\Set;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deck>
 */
class DeckFactory extends Factory
{
    protected $model = Deck::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'set_id' => Set::factory(),
            'tcgplayer_id' => fake()->unique()->numberBetween(100_000, 999_999),
            'product_name' => fake()->unique()->words(3, true),
            'rarity' => 'Deck',
            'condition' => 'Unopened',
            'market_price' => fake()->numberBetween(1_000, 10_000),
            'low_price' => fake()->numberBetween(500, 9_500),
        ];
    }

    public function unopened(): static
    {
        return $this->state(fn () => ['condition' => 'Unopened']);
    }

    public function opened(): static
    {
        return $this->state(fn () => ['condition' => 'Opened']);
    }
}
