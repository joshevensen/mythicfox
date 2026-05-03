<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    public function download(File $file): RedirectResponse|StreamedResponse
    {
        if ($file->expired_at !== null) {
            abort(410, 'File has expired and is no longer available.');
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($file->file_path)) {
            abort(404, 'File missing from storage.');
        }

        try {
            $url = $disk->temporaryUrl($file->file_path, Carbon::now()->addMinutes(5));

            return redirect()->away($url);
        } catch (\RuntimeException) {
            // Local disk doesn't support temporary URLs — stream the file directly.
            return $disk->download($file->file_path, $file->original_filename);
        }
    }
}
