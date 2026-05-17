<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Jobs\ImportOrdersJob;
use App\Models\File;
use App\Support\FilePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrdersImportController extends Controller
{
    private const MAX_FILE_BYTES = 200 * 1024 * 1024;

    public function store(Request $request): RedirectResponse
    {
        $maxKb = (int) (self::MAX_FILE_BYTES / 1024);

        $request->validate([
            'orderlist' => ['required', 'file', 'mimes:csv,txt', 'max:'.$maxKb],
            'shipping_export' => ['nullable', 'file', 'mimes:csv,txt', 'max:'.$maxKb],
            'pull_sheet' => ['nullable', 'file', 'mimes:csv,txt', 'max:'.$maxKb],
            'packing_slips' => ['nullable', 'file', Rule::file()->extensions(['pdf']), 'max:'.$maxKb],
        ]);

        $orderListFile = $this->persistUpload($request, 'orderlist');
        $shippingFile = $this->persistUpload($request, 'shipping_export');
        $pullSheetFile = $this->persistUpload($request, 'pull_sheet');
        $packingSlipFile = $this->persistUpload($request, 'packing_slips');

        Cache::put(ImportOrdersJob::IN_FLIGHT_CACHE_KEY, true, now()->addHour());
        Cache::forget(ImportOrdersJob::LAST_RESULT_CACHE_KEY);

        ImportOrdersJob::dispatch(
            $orderListFile->id,
            $shippingFile?->id,
            $pullSheetFile?->id,
            $packingSlipFile?->id,
        );

        return back()->with('toast', [
            'kind' => 'success',
            'message' => 'Import queued — processing orders…',
        ]);
    }

    private function persistUpload(Request $request, string $key): ?File
    {
        if (! $request->hasFile($key)) {
            return null;
        }

        $upload = $request->file($key);
        $original = $upload->getClientOriginalName();
        $storagePath = FilePath::build('imports', 'orders', $original);

        $contents = file_get_contents($upload->getRealPath());
        Storage::put($storagePath, $contents !== false ? $contents : '');

        return File::create([
            'type' => 'import',
            'file_path' => $storagePath,
            'original_filename' => $original,
            'uploaded_at' => Carbon::now(),
        ]);
    }
}
