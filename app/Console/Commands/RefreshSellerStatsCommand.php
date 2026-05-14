<?php

namespace App\Console\Commands;

use App\Jobs\RefreshSellerStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class RefreshSellerStatsCommand extends Command
{
    protected $signature = 'seller-stats:refresh';

    protected $description = 'Refresh seller stats from the public TCGPlayer storefront (runs synchronously)';

    public function handle(): int
    {
        $this->info('Refreshing seller stats…');

        Bus::dispatchSync(new RefreshSellerStats);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
