<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Set;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Set>
 */
class SetFactory extends Factory
{
    protected $model = Set::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->unique()->words(3, true),
            'base_price' => null,
            'high_price' => null,
            'market_offset' => null,
            'high_offset' => null,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn () => ['product_id' => $product->id]);
    }

    public function synced(): static
    {
        return $this->state(fn () => ['cards_synced_at' => now()]);
    }

    public function unsynced(): static
    {
        return $this->state(fn () => ['cards_synced_at' => null]);
    }
}
