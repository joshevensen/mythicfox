<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    private const Conditions = [
        'Near Mint',
        'Lightly Played',
        'Moderately Played',
        'Damaged',
        'Near Mint Foil',
        'Lightly Played Foil',
    ];

    private const ProductLines = ['Magic', 'Lorcana', 'Flesh and Blood'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(25, 5_000);
        $quantity = fake()->numberBetween(1, 4);

        return [
            'order_id' => Order::factory(),
            'product_line' => fake()->randomElement(self::ProductLines),
            'set_name' => fake()->words(3, true),
            'product_name' => fake()->words(2, true),
            'number' => (string) fake()->numberBetween(1, 300),
            'rarity' => fake()->randomElement(['C', 'U', 'R', 'M']),
            'condition' => fake()->randomElement(self::Conditions),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
            'tcgplayer_sku_id' => fake()->numberBetween(1_000_000, 9_999_999),
        ];
    }

    public function withoutPrice(): static
    {
        return $this->state(fn () => [
            'unit_price' => null,
            'total_price' => null,
        ]);
    }

    public function forCard(Card $card): static
    {
        return $this->state(fn () => [
            'product_line' => $card->set->product->name,
            'set_name' => $card->set->name,
            'product_name' => $card->product_name,
            'number' => $card->number,
            'rarity' => $card->rarity,
            'condition' => $card->condition,
        ]);
    }
}
