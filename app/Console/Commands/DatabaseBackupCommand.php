<?php

namespace App\Console\Commands;

use App\Jobs\BackupDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Back up the PostgreSQL database via pg_dump and upload to DO Spaces';

    public function handle(): int
    {
        $this->info('Running database backup…');

        Bus::dispatchSync(new BackupDatabase);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
