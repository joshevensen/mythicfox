<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    public function download(File $file): JsonResponse
    {
        if ($file->expired_at !== null) {
            return response()->json(
                ['message' => 'File has expired and is no longer available.'],
                410,
            );
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($file->file_path)) {
            return response()->json(
                ['message' => 'File missing from storage.'],
                404,
            );
        }

        try {
            $url = $disk->temporaryUrl(
                $file->file_path,
                Carbon::now()->addMinutes(5),
            );
        } catch (\RuntimeException) {
            // Local disk doesn't support signed URLs. Fall back to a streaming
            // route on the same domain — the client opens it the same way.
            $url = route('settings.files.stream', $file);
        }

        return response()->json(['url' => $url]);
    }

    public function stream(File $file): StreamedResponse
    {
        if ($file->expired_at !== null) {
            abort(410, 'File has expired and is no longer available.');
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($file->file_path)) {
            abort(404, 'File missing from storage.');
        }

        return $disk->download($file->file_path, $file->original_filename);
    }
}
