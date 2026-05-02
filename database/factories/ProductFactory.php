<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'base_price' => 25,
            'high_price' => 1000,
            'market_offset' => 0,
            'high_offset' => 15,
            'priced_at' => null,
        ];
    }

    public function magic(): static
    {
        return $this->state(fn () => ['name' => 'Magic']);
    }

    public function lorcana(): static
    {
        return $this->state(fn () => ['name' => 'Lorcana TCG']);
    }

    public function fleshAndBlood(): static
    {
        return $this->state(fn () => ['name' => 'Flesh & Blood TCG']);
    }
}
