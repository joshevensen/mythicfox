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

        return $this->parseInto($file, $sourcePath);
    }

    /**
     * Run the importer against a CSV at $localPath whose audit `files` row
     * has already been persisted by an upstream caller (e.g. an HTTP upload
     * handler that wants to dispatch the import to a queue).
     */
    public function importPrePersisted(File $file, string $localPath): PricingCustomExportResult
    {
        if (! is_readable($localPath)) {
            throw new RuntimeException("Cannot read source CSV at [{$localPath}]");
        }

        return $this->parseInto($file, $localPath);
    }

    private function parseInto(File $file, string $sourcePath): PricingCustomExportResult
    {
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

        $touchedIds = $this->upserter->touchedProductIds();

        return new PricingCustomExportResult(
            file: $file,
            rowsProcessed: $rowsProcessed,
            productsTouched: count($touchedIds),
            productIds: $touchedIds,
        );
    }
}
