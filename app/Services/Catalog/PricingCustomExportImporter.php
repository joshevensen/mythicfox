<?php

namespace App\Services\Catalog;

use App\Models\File;
use App\Services\Catalog\Support\ImportPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PricingCustomExportImporter
{
    public function __construct(
        private readonly CatalogUpserter $upserter,
    ) {}

    public function import(string $sourcePath, ?string $originalFilename = null): PricingCustomExportResult
    {
        if (! is_readable($sourcePath)) {
            throw new RuntimeException("Cannot read source CSV at [{$sourcePath}]");
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

        $rowsProcessed = 0;

        DB::transaction(function () use ($sourcePath, &$rowsProcessed) {
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
                    $this->upserter->ingest($assoc);
                    $rowsProcessed++;
                }
            } finally {
                fclose($handle);
            }

            $this->upserter->flush();
            $this->upserter->bumpPricedAt();
        });

        return new PricingCustomExportResult(
            file: $file,
            rowsProcessed: $rowsProcessed,
            productsTouched: count($this->upserter->touchedProductIds()),
        );
    }
}
