<?php

namespace Database\Factories;

use App\Models\CardSet;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardSet>
 */
class CardSetFactory extends Factory
{
    protected $model = CardSet::class;

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
}
