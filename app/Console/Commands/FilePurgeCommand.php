<?php

namespace App\Console\Commands;

use App\Jobs\PurgeExpiredFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class FilePurgeCommand extends Command
{
    protected $signature = 'files:purge';

    protected $description = 'Purge import files older than 90 days from storage (audit rows preserved)';

    public function handle(): int
    {
        $this->info('Purging expired import files…');

        Bus::dispatchSync(new PurgeExpiredFiles);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
