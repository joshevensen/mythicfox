<?php

namespace App\Services\Catalog\Sync;

readonly class SyncResult
{
    public function __construct(
        public int $setsUpserted,
        public int $cardsSynced,
        public int $setsSynced,
    ) {}
}
