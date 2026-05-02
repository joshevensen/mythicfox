<?php

namespace App\Services\Catalog;

use App\Models\Card;
use App\Models\File;
use App\Models\Inventory;
use App\Services\Catalog\Support\CentsParser;
use App\Services\Catalog\Support\ImportPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MyPricingImporter
{
    public function __construct(
        private readonly CatalogUpserter $upserter,
    ) {}

    public function import(
        string $sourcePath,
        MyPricingImportMode $mode,
        ?string $originalFilename = null,
        bool $force = false,
    ): MyPricingResult {
        if (! is_readable($sourcePath)) {
            throw new RuntimeException("Cannot read source CSV at [{$sourcePath}]");
        }

        if ($mode === MyPricingImportMode::Bootstrap && ! $force && Inventory::query()->exists()) {
            throw new RuntimeException(
                'Bootstrap refuses to run because inventory already has rows. Re-run with force=true to overwrite.'
            );
        }

        $originalFilename = $originalFilename ?: basename($sourcePath);
        $storedPath = ImportPath::for('pricing', $originalFilename);

        Storage::put($storedPath, file_get_contents($sourcePath));

        $file = File::create([
            'type' => 'import',
            'file_path' => $storedPath,
            'original_filename' => $originalFilename,
            'uploaded_at' => Carbon::now(),
        ]);

        return $mode === MyPricingImportMode::Bootstrap
            ? $this->runBootstrap($sourcePath, $file)
            : $this->runReconciliation($sourcePath, $file);
    }

    private function runBootstrap(string $sourcePath, File $file): MyPricingResult
    {
        $rowsProcessed = 0;
        $pendingInventory = [];

        DB::transaction(function () use ($sourcePath, &$rowsProcessed, &$pendingInventory) {
            $this->streamRows($sourcePath, function (array $row) use (&$rowsProcessed, &$pendingInventory) {
                $tcgplayerId = (int) ($row['TCGplayer Id'] ?? 0);
                if ($tcgplayerId === 0) {
                    return;
                }

                $this->upserter->ingest($row);
                $rowsProcessed++;

                $pendingInventory[$tcgplayerId] = max(0, (int) ($row['Total Quantity'] ?? 0));
            });

            $this->upserter->flush();
            $this->upserter->bumpPricedAt();
        });

        $inventoryRowsWritten = $this->writeInventory($pendingInventory);

        return new MyPricingResult(
            file: $file,
            mode: MyPricingImportMode::Bootstrap,
            rowsProcessed: $rowsProcessed,
            inventoryRowsWritten: $inventoryRowsWritten,
            discrepancies: [],
            missingLocally: [],
            localOnly: [],
        );
    }

    /**
     * @param  array<int, int>  $tcgplayerIdToQuantity
     */
    private function writeInventory(array $tcgplayerIdToQuantity): int
    {
        if ($tcgplayerIdToQuantity === []) {
            return 0;
        }

        $idMap = Card::whereIn('tcgplayer_id', array_keys($tcgplayerIdToQuantity))
            ->pluck('id', 'tcgplayer_id')
            ->all();

        $written = 0;
        DB::transaction(function () use ($tcgplayerIdToQuantity, $idMap, &$written) {
            foreach ($tcgplayerIdToQuantity as $tcgplayerId => $quantity) {
                $cardId = $idMap[$tcgplayerId] ?? null;
                if ($cardId === null) {
                    continue;
                }

                Inventory::updateOrCreate(
                    ['card_id' => $cardId],
                    ['quantity' => $quantity],
                );
                $written++;
            }
        });

        return $written;
    }

    private function runReconciliation(string $sourcePath, File $file): MyPricingResult
    {
        $rowsProcessed = 0;
        $discrepancies = [];
        $missingLocally = [];
        $seenTcgplayerIds = [];

        $this->streamRows($sourcePath, function (array $row) use (
            &$rowsProcessed, &$discrepancies, &$missingLocally, &$seenTcgplayerIds
        ) {
            $tcgplayerId = (int) ($row['TCGplayer Id'] ?? 0);
            if ($tcgplayerId === 0) {
                return;
            }

            $rowsProcessed++;
            $seenTcgplayerIds[$tcgplayerId] = true;

            $card = Card::where('tcgplayer_id', $tcgplayerId)
                ->with('inventory')
                ->first();

            if (! $card) {
                $missingLocally[] = $tcgplayerId;

                return;
            }

            $csvQuantity = (int) ($row['Total Quantity'] ?? 0);
            $csvMarketplacePrice = CentsParser::parse($row['TCG Marketplace Price'] ?? null);

            $localQuantity = $card->inventory?->quantity ?? 0;
            $localEffective = $card->inventory?->effective_price;

            if ($localQuantity !== $csvQuantity || $localEffective !== $csvMarketplacePrice) {
                $discrepancies[] = [
                    'tcgplayer_id' => $tcgplayerId,
                    'local_quantity' => $localQuantity,
                    'csv_quantity' => $csvQuantity,
                    'local_effective_price' => $localEffective,
                    'csv_marketplace_price' => $csvMarketplacePrice,
                ];
            }
        });

        $localOnly = Inventory::query()
            ->join('cards', 'cards.id', '=', 'inventory.card_id')
            ->whereNotIn('cards.tcgplayer_id', array_keys($seenTcgplayerIds))
            ->pluck('cards.tcgplayer_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return new MyPricingResult(
            file: $file,
            mode: MyPricingImportMode::Reconciliation,
            rowsProcessed: $rowsProcessed,
            inventoryRowsWritten: 0,
            discrepancies: $discrepancies,
            missingLocally: $missingLocally,
            localOnly: $localOnly,
        );
    }

    /**
     * @param  callable(array<string, string|null>): void  $callback
     */
    private function streamRows(string $sourcePath, callable $callback): void
    {
        $handle = fopen($sourcePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Failed to open [{$sourcePath}] for reading");
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new RuntimeException('CSV is empty');
            }

            while (($row = fgetcsv($handle)) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }
                $assoc = array_combine($header, $row);
                if ($assoc === false) {
                    continue;
                }
                $callback($assoc);
            }
        } finally {
            fclose($handle);
        }
    }
}
