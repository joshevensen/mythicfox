<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Models\File;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerStats;
use App\Models\Set;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetAccountCommand extends Command
{
    protected $signature = 'account:reset {--force : Allow execution in production}';

    protected $description = 'Delete all imported data and storage files, leaving users intact.';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refused to run in production without --force.');

            return self::FAILURE;
        }

        $orders = Order::count();
        $items = OrderItem::count();
        $inventory = Inventory::count();
        $products = Product::count();
        $sets = Set::count();
        $cards = Card::count();
        $sellerStats = SellerStats::count();
        $files = File::count();

        $disk = Storage::disk(config('filesystems.default'));
        $storagePaths = ['imports', 'exports'];
        $storageFiles = 0;
        foreach ($storagePaths as $prefix) {
            $storageFiles += count($disk->allFiles($prefix));
        }

        if ($orders + $items + $inventory + $products + $sets + $cards + $sellerStats + $files + $storageFiles === 0) {
            $this->info('Nothing to reset.');

            return self::SUCCESS;
        }

        foreach ($storagePaths as $prefix) {
            foreach ($disk->allFiles($prefix) as $file) {
                $disk->delete($file);
            }
        }

        DB::statement('TRUNCATE TABLE order_items, orders RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE inventory RESTART IDENTITY');
        DB::statement('TRUNCATE TABLE cards, sets, products RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE seller_stats RESTART IDENTITY');
        DB::statement('TRUNCATE TABLE files RESTART IDENTITY');

        $this->info('Account reset complete. Users preserved.');

        return self::SUCCESS;
    }
}
