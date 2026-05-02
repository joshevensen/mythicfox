<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

/**
 * Development-only seed data for the orders domain. About 25 orders across the
 * last 90 days, mostly Completed - Paid with a few Canceled and a few shipped.
 * Each order gets 1–4 line items. NO inventory decrement runs from this
 * seeder — it's data-only for manual UI exploration.
 *
 * Tests don't run this; tests build orders directly via the factories.
 */
class OrderSeeder extends Seeder
{
    public function run(): void
    {
        Order::factory()
            ->count(20)
            ->has(OrderItem::factory()->count(fake()->numberBetween(1, 4)), 'items')
            ->create();

        Order::factory()
            ->count(3)
            ->canceled()
            ->create();

        Order::factory()
            ->count(2)
            ->shipped()
            ->has(OrderItem::factory()->count(fake()->numberBetween(1, 4)), 'items')
            ->create();
    }
}
