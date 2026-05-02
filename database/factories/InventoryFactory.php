<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'calculated_price' => null,
            'override_price' => null,
            'last_exported_price' => null,
        ];
    }
}
