<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\CardSet;
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
            'set_id' => CardSet::factory(),
            'tcgplayer_id' => fake()->unique()->numberBetween(100_000, 999_999),
            'product_name' => fake()->unique()->words(3, true),
            'number' => (string) fake()->numberBetween(1, 300),
            'rarity' => fake()->randomElement(['C', 'U', 'R', 'M']),
            'condition' => 'Near Mint',
            'market_price' => fake()->numberBetween(50, 5_000),
            'low_price' => fake()->numberBetween(25, 4_500),
        ];
    }
}
