<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearOrdersCommand extends Command
{
    private const ORDER_FILE_PREFIX = 'imports/orders/';

    protected $signature = 'orders:clear {--force : Skip confirmation prompt}';

    protected $description = 'Delete all orders, order items, and order import files. Does not touch users or catalog.';

    public function handle(): int
    {
        $orders = Order::count();
        $items = OrderItem::count();
        $orderFiles = File::query()->where('file_path', 'like', self::ORDER_FILE_PREFIX.'%');
        $files = (clone $orderFiles)->count();

        if ($orders === 0 && $items === 0 && $files === 0) {
            $this->info('Nothing to clear.');

            return self::SUCCESS;
        }

        $this->warn("This will delete {$orders} order(s), {$items} order item(s), and {$files} order import file(s).");

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        $disk = Storage::disk(config('filesystems.default'));
        $missing = 0;
        (clone $orderFiles)->select(['id', 'file_path'])->chunkById(500, function ($rows) use ($disk, &$missing) {
            foreach ($rows as $row) {
                if ($disk->exists($row->file_path)) {
                    $disk->delete($row->file_path);
                } else {
                    $missing++;
                }
            }
        });

        DB::statement('TRUNCATE TABLE order_items, orders RESTART IDENTITY CASCADE');
        (clone $orderFiles)->delete();

        $this->info("Cleared {$orders} order(s), {$items} order item(s), and {$files} order import file(s).");

        if ($missing > 0) {
            $this->line("({$missing} blob(s) were already missing from storage.)");
        }

        return self::SUCCESS;
    }
}
