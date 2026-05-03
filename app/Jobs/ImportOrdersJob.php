<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\Orders\OrderImporter;
use App\Services\Orders\OrderImportInput;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const IN_FLIGHT_CACHE_KEY = 'orders:import-in-flight';

    public int $timeout = 300;

    public function __construct(
        public readonly int $orderListFileId,
        public readonly ?int $shippingExportFileId = null,
        public readonly ?int $pullSheetFileId = null,
        public readonly ?int $packingSlipFileId = null,
    ) {}

    public function handle(OrderImporter $importer): void
    {
        $tmpPaths = [];

        try {
            $orderListFile = File::query()->findOrFail($this->orderListFileId);
            $shippingFile = $this->shippingExportFileId !== null
                ? File::query()->find($this->shippingExportFileId)
                : null;
            $pullSheetFile = $this->pullSheetFileId !== null
                ? File::query()->find($this->pullSheetFileId)
                : null;
            $pdfFile = $this->packingSlipFileId !== null
                ? File::query()->find($this->packingSlipFileId)
                : null;

            $tmpOrderList = $this->materialize($orderListFile, $tmpPaths);
            $tmpShipping = $shippingFile ? $this->materialize($shippingFile, $tmpPaths) : null;
            $tmpPullSheet = $pullSheetFile ? $this->materialize($pullSheetFile, $tmpPaths) : null;
            $tmpPdf = $pdfFile ? $this->materialize($pdfFile, $tmpPaths) : null;

            $input = new OrderImportInput(
                orderListPath: $tmpOrderList,
                shippingExportPath: $tmpShipping,
                pullSheetPath: $tmpPullSheet,
                packingSlipPdfPath: $tmpPdf,
                orderListFilename: $orderListFile->original_filename,
                shippingExportFilename: $shippingFile?->original_filename,
                pullSheetFilename: $pullSheetFile?->original_filename,
                packingSlipFilename: $pdfFile?->original_filename,
            );

            $existingFiles = array_values(array_filter([
                $orderListFile,
                $shippingFile,
                $pullSheetFile,
                $pdfFile,
            ]));

            $result = $importer->importPrePersisted($input, $existingFiles);

            if ($result->errors !== []) {
                Log::warning('Order import completed with errors', ['errors' => $result->errors]);
            }
        } finally {
            foreach ($tmpPaths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            Cache::forget(self::IN_FLIGHT_CACHE_KEY);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Cache::forget(self::IN_FLIGHT_CACHE_KEY);
    }

    /**
     * @param  array<int, string>  $tmpPaths
     */
    private function materialize(File $file, array &$tmpPaths): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $contents = $disk->get($file->file_path);

        if ($contents === null) {
            throw new \RuntimeException("File [{$file->id}] is missing from storage at {$file->file_path}.");
        }

        $tmp = tempnam(sys_get_temp_dir(), 'orders-import-');

        if ($tmp === false) {
            throw new \RuntimeException('Could not allocate a temporary file for order import.');
        }

        file_put_contents($tmp, $contents);
        $tmpPaths[] = $tmp;

        return $tmp;
    }
}
