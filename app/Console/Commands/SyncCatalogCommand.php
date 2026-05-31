<?php

namespace App\Console\Commands;

use App\Services\Catalog\Sync\CatalogSyncService;
use Illuminate\Console\Command;

class SyncCatalogCommand extends Command
{
    protected $signature = 'catalog:sync {game? : magic, lorcana, or fab} {--force : Re-sync sets that already have cards_synced_at set}';

    protected $description = 'Sync catalog sets, cards, and printings from external sources';

    public function handle(CatalogSyncService $service): int
    {
        $game = $this->argument('game');
        $force = (bool) $this->option('force');

        $games = $game !== null ? [$game] : CatalogSyncService::games();

        foreach ($games as $g) {
            $this->info('Syncing '.ucfirst($g).' sets...');
            $result = $service->sync($g, $force);
            $this->line("  Sets upserted: {$result->setsUpserted}");
            $this->line("  Sets synced: {$result->setsSynced}");
            $this->line("  Cards synced: {$result->cardsSynced}");
        }

        return self::SUCCESS;
    }
}
