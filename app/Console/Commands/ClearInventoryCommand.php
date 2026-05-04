<?php

namespace App\Console\Commands;

use App\Models\Inventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearInventoryCommand extends Command
{
    protected $signature = 'inventory:clear {--force : Skip confirmation prompt}';

    protected $description = 'Delete all inventory rows. Does not touch the cards they reference.';

    public function handle(): int
    {
        $count = Inventory::count();

        if ($count === 0) {
            $this->info('Nothing to clear.');

            return self::SUCCESS;
        }

        $this->warn("This will delete {$count} inventory row(s).");

        if (! $this->option('force') && ! $this->confirm('Continue?', false)) {
            $this->line('Aborted.');

            return self::FAILURE;
        }

        DB::statement('TRUNCATE TABLE inventory RESTART IDENTITY');

        $this->info("Cleared {$count} inventory row(s).");

        return self::SUCCESS;
    }
}
