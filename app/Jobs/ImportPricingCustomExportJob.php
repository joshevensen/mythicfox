<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\Product;
use App\Services\Catalog\PricingCustomExportImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportPricingCustomExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const IN_FLIGHT_CACHE_KEY = 'catalog:import-in-flight';

    public const LAST_RESULT_CACHE_KEY = 'catalog:import-last-result';

    public int $timeout = 600;

    public function __construct(public readonly int $fileId) {}

    public function handle(PricingCustomExportImporter $importer): void
    {
        $tmpPath = null;

        try {
            $file = File::query()->findOrFail($this->fileId);
            $tmpPath = $this->materialize($file);

            $result = $importer->importPrePersisted($file, $tmpPath);

            $touchedNames = $result->productIds === []
                ? []
                : Product::query()
                    ->whereIn('id', $result->productIds)
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();

            Cache::put(self::LAST_RESULT_CACHE_KEY, [
                'success' => true,
                'rows_processed' => $result->rowsProcessed,
                'products_touched' => $result->productsTouched,
                'product_label' => $touchedNames !== []
                    ? implode(', ', $touchedNames)
                    : 'the catalog',
                'completed_at' => now()->toIso8601String(),
            ], now()->addHour());
        } catch (Throwable $e) {
            Log::warning('PricingCustomExport import failed', [
                'file_id' => $this->fileId,
                'message' => $e->getMessage(),
            ]);

            Cache::put(self::LAST_RESULT_CACHE_KEY, [
                'success' => false,
                'message' => $e->getMessage(),
                'completed_at' => now()->toIso8601String(),
            ], now()->addHour());

            throw $e;
        } finally {
            if ($tmpPath !== null && is_file($tmpPath)) {
                @unlink($tmpPath);
            }

            Cache::forget(self::IN_FLIGHT_CACHE_KEY);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::IN_FLIGHT_CACHE_KEY);
    }

    private function materialize(File $file): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $contents = $disk->get($file->file_path);

        if ($contents === null) {
            throw new RuntimeException("File [{$file->id}] is missing from storage at {$file->file_path}.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'catalog-import-');

        if ($tmp === false) {
            throw new RuntimeException('Could not allocate a temporary file for catalog import.');
        }

        file_put_contents($tmp, $contents);

        return $tmp;
    }
}
