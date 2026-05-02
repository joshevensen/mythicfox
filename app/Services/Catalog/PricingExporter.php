<?php

namespace App\Services\Catalog;

use App\Models\File;
use App\Models\Inventory;
use App\Services\Catalog\Support\ExportPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PricingExporter
{
    /**
     * 16 columns in TCGPlayer's MyPricing order. Header matches verbatim
     * because TCGPlayer rejects re-ordered columns on import.
     */
    public const Header = [
        'TCGplayer Id',
        'Product Line',
        'Set Name',
        'Product Name',
        'Title',
        'Number',
        'Rarity',
        'Condition',
        'TCG Market Price',
        'TCG Direct Low',
        'TCG Low Price With Shipping',
        'TCG Low Price',
        'Total Quantity',
        'Add to Quantity',
        'TCG Marketplace Price',
        'Photo URL',
    ];

    public function export(): PricingExportResult
    {
        // TODO: TCGPlayer's MyPricing importer historically reads only the
        // seller's TCG Marketplace Price column and ignores the rest. Verify
        // on the first real round-trip whether empty cells for TCG Direct
        // Low / TCG Low Price With Shipping / Photo URL are accepted; if
        // rejected, backfill those with TCG Low Price (or sane defaults).
        $tempPath = tempnam(sys_get_temp_dir(), 'mythic-fox-pricing-').'.csv';
        $handle = fopen($tempPath, 'w');
        if ($handle === false) {
            throw new RuntimeException("Failed to open temp file [{$tempPath}] for writing");
        }

        $rowsWritten = 0;

        try {
            fputcsv($handle, self::Header);

            Inventory::query()
                ->with('card.set.product')
                ->orderBy('id')
                ->chunkById(500, function ($chunk) use ($handle, &$rowsWritten) {
                    foreach ($chunk as $inventory) {
                        fputcsv($handle, $this->rowFor($inventory));
                        $rowsWritten++;
                    }
                });
        } finally {
            fclose($handle);
        }

        $storagePath = ExportPath::for('pricing', 'mythic-fox-pricing');
        $stored = Storage::put($storagePath, file_get_contents($tempPath));
        @unlink($tempPath);

        if ($stored === false) {
            throw new RuntimeException("Failed to persist export to [{$storagePath}]");
        }

        $file = DB::transaction(function () use ($storagePath) {
            $file = File::create([
                'type' => 'export',
                'file_path' => $storagePath,
                'original_filename' => basename($storagePath),
                'uploaded_at' => Carbon::now(),
            ]);

            // Set last_exported_price = effective price for every row using a
            // single SQL update so we don't pay an N-row Eloquent round-trip.
            DB::statement(
                'UPDATE inventory SET last_exported_price = COALESCE(override_price, calculated_price), updated_at = NOW()'
            );

            return $file;
        });

        return new PricingExportResult(
            file: $file,
            rowsWritten: $rowsWritten,
        );
    }

    /**
     * @return list<int|string>
     */
    private function rowFor(Inventory $inventory): array
    {
        $card = $inventory->card;
        $set = $card->set;
        $product = $set->product;

        $effective = $inventory->effective_price;

        return [
            $card->tcgplayer_id,
            $product->name,
            $set->name,
            $card->product_name,
            '',
            $card->number,
            $card->rarity,
            $card->condition,
            self::cents($card->market_price),
            '',
            '',
            self::cents($card->low_price),
            $inventory->quantity,
            0,
            self::cents($effective),
            '',
        ];
    }

    private static function cents(?int $cents): string
    {
        if ($cents === null) {
            return '';
        }

        return number_format($cents / 100, 2, '.', '');
    }
}
