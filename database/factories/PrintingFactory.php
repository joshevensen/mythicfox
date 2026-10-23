<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Printing;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

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
            'nm_market_price' => fake()->numberBetween(50, 5_000),
            'nm_low_price' => fake()->numberBetween(25, 4_500),
            'lp_market_price' => fake()->numberBetween(50, 5_000),
            'lp_low_price' => fake()->numberBetween(25, 4_500),
            'mp_market_price' => fake()->numberBetween(50, 5_000),
            'mp_low_price' => fake()->numberBetween(25, 4_500),
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

    /**
     * Sets one condition's market/low pair, leaving the other conditions untouched.
     *
     * @param  'nm'|'lp'|'mp'  $condition
     */
    public function withPricing(string $condition, ?int $marketCents, ?int $lowCents): static
    {
        if (! in_array($condition, ['nm', 'lp', 'mp'], true)) {
            throw new InvalidArgumentException("Unknown condition [{$condition}]; expected nm, lp or mp.");
        }

        return $this->state(fn () => [
            "{$condition}_market_price" => $marketCents,
            "{$condition}_low_price" => $lowCents,
        ]);
    }

    public function withoutPricing(): static
    {
        return $this->state(fn () => [
            'nm_market_price' => null,
            'nm_low_price' => null,
            'lp_market_price' => null,
            'lp_low_price' => null,
            'mp_market_price' => null,
            'mp_low_price' => null,
        ]);
    }
}
