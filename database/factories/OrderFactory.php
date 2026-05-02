<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sellerId = (string) (config('services.tcgplayer.seller_id') ?: '623394E9');
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        $product = fake()->numberBetween(50, 5_000);
        $shipping = fake()->randomElement([0, 199, 299]);

        return [
            'tcgplayer_order_number' => strtoupper($sellerId).'-'.strtoupper(Str::random(6)).'-'.strtoupper(Str::random(5)),
            'tcgplayer_status' => 'Completed - Paid',
            'buyer_firstname' => $firstName,
            'buyer_lastname' => $lastName,
            'buyer_name' => "{$firstName} {$lastName}",
            'address1' => fake()->streetAddress(),
            'address2' => null,
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            'order_date' => Carbon::now()->subDays(fake()->numberBetween(0, 90))->toDateString(),
            'shipping_method' => 'Standard (7-10 days)',
            'item_count' => fake()->numberBetween(1, 10),
            'product_weight' => fake()->randomFloat(2, 0.10, 2.00),
            'product_amount' => $product,
            'shipping_amount' => $shipping,
            'total_amount' => $product + $shipping,
            'buyer_paid' => true,
            'tracking_number' => null,
            'carrier' => null,
            'imported_at' => Carbon::now(),
        ];
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'tcgplayer_status' => 'Canceled',
            'buyer_firstname' => null,
            'buyer_lastname' => null,
            'address1' => null,
            'address2' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
            'shipping_method' => null,
            'item_count' => null,
            'product_weight' => null,
            'tracking_number' => null,
            'carrier' => null,
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn () => [
            'tracking_number' => '94055'.fake()->numerify('##################'),
            'carrier' => 'USPS',
        ]);
    }

    public function withoutLinePrices(): static
    {
        // Marker state — pairs with OrderItemFactory::withoutPrice() at the
        // call site (e.g. Order::factory()->withoutLinePrices()
        //   ->has(OrderItem::factory()->withoutPrice()->count(2))).
        return $this;
    }
}
