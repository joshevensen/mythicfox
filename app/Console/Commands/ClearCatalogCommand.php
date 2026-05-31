<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Set;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearCatalogCommand extends Command
{
    protected $signature = 'catalog:clear {--force : Skip confirmation prompt}';

    protected $description = 'Delete all products, sets, and cards. Refuses if inventory still references cards — run inventory:clear first.';

    public function handle(): int
    {
        if (Inventory::query()->exists()) {
            $this->error('Inventory rows still reference cards. Run `inventory:clear` first.');

            return self::FAILURE;
        }

        $products = Product::count();
        $sets = Set::count();
        $cards = Card::count();

        if ($products === 0 && $sets === 0 && $cards === 0) {
            $this->info('Nothing to clear.');

            return self::SUCCESS;
        }

        $this->warn("This will delete {$products} product(s), {$sets} set(s), and {$cards} card(s).");

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        DB::statement('TRUNCATE TABLE cards, sets, products RESTART IDENTITY CASCADE');

        $this->info("Cleared {$products} product(s), {$sets} set(s), and {$cards} card(s).");

        return self::SUCCESS;
    }
}
