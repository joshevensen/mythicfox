<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Jobs\ImportPricingCustomExportJob;
use App\Models\File;
use App\Support\FilePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CatalogUploadController extends Controller
{
    private const MAX_FILE_BYTES = 200 * 1024 * 1024;

    /**
     * The non-pricing PricingCustomExport columns we require for a
     * file to be a recognisable catalog import. The full 16-column header is
     * documented in docs/catalog-schema.md#column---field-map; checking the
     * identity columns is enough to catch obvious mis-uploads.
     */
    private const REQUIRED_HEADER_COLUMNS = [
        'TCGplayer Id',
        'Product Line',
        'Set Name',
        'Product Name',
        'Number',
        'Rarity',
        'Condition',
        'TCG Market Price',
        'TCG Low Price',
    ];

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) (self::MAX_FILE_BYTES / 1024);

        $request->validate([
            'file' => [
                'required',
                'file',
                Rule::file()->extensions(['csv']),
                'max:'.$maxKb,
            ],
        ]);

        $file = $this->persistUpload($request);

        $headerError = $this->validateHeader($file);

        if ($headerError !== null) {
            return back()->with('catalog_upload_error', $headerError);
        }

        Cache::put(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY, true, now()->addHour());
        Cache::forget(ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY);

        ImportPricingCustomExportJob::dispatch($file->id);

        return back()->with('toast', [
            'kind' => 'success',
            'message' => 'PricingCustomExport queued — refreshing catalog…',
        ]);
    }

    private function persistUpload(Request $request): File
    {
        $upload = $request->file('file');
        $original = $upload->getClientOriginalName();
        $storagePath = FilePath::build('imports', 'pricing', $original);

        Storage::putFileAs(dirname($storagePath), $upload, basename($storagePath));

        return File::create([
            'type' => 'import',
            'file_path' => $storagePath,
            'original_filename' => $original,
            'uploaded_at' => Carbon::now(),
        ]);
    }

    private function validateHeader(File $file): ?string
    {
        $stream = Storage::readStream($file->file_path);

        if ($stream === null) {
            return 'The uploaded file is empty.';
        }

        $headerLine = fgets($stream);
        fclose($stream);

        if ($headerLine === false || trim($headerLine) === '') {
            return 'The uploaded file is empty.';
        }

        $headers = str_getcsv(trim($headerLine, "\r\n"));
        $missing = array_values(array_diff(self::REQUIRED_HEADER_COLUMNS, $headers));

        if ($missing !== []) {
            return sprintf(
                "We couldn't read this file as a PricingCustomExport. Missing columns: %s.",
                implode(', ', $missing),
            );
        }

        return null;
    }
}
