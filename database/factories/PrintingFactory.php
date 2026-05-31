<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Printing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Printing>
 */
class PrintingFactory extends Factory
{
    protected $model = Printing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'finish' => 'non-foil',
            'tcgplayer_id' => fake()->unique()->numberBetween(100_000, 999_999),
            'justtcg_id' => null,
            'other_ids' => null,
            'image_url' => null,
            'market_price' => fake()->numberBetween(50, 5_000),
            'low_price' => fake()->numberBetween(25, 4_500),
        ];
    }

    public function foil(): static
    {
        return $this->state(fn () => ['finish' => 'foil']);
    }

    public function nonFoil(): static
    {
        return $this->state(fn () => ['finish' => 'non-foil']);
    }

    public function etched(): static
    {
        return $this->state(fn () => ['finish' => 'etched']);
    }

    public function withPricing(int $marketCents, ?int $lowCents): static
    {
        return $this->state(fn () => [
            'market_price' => $marketCents,
            'low_price' => $lowCents,
        ]);
    }
}
