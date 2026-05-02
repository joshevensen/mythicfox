<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public const CanonicalProducts = ['Magic', 'Lorcana TCG', 'Flesh & Blood TCG'];

    public function run(): void
    {
        foreach (self::CanonicalProducts as $name) {
            Product::firstOrCreate(['name' => $name], [
                'base_price' => 25,
                'high_price' => 1000,
                'market_offset' => 0,
                'high_offset' => 15,
            ]);
        }
    }
}
