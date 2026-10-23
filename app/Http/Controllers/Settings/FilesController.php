<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    private const FILES_PER_PAGE = 20;

    public function index(Request $request): Response
    {
        return Inertia::render('settings/Files', [
            'files' => $this->loadFiles($request),
            'filePurposes' => $this->purposeOptions(),
        ]);
    }

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

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function loadFiles(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = self::FILES_PER_PAGE;

        $sortColumn = (string) $request->query('sort', 'uploaded_at');
        $sortDir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $sortable = ['original_filename', 'type', 'purpose', 'uploaded_at', 'status'];

        if (! in_array($sortColumn, $sortable, true)) {
            $sortColumn = 'uploaded_at';
        }

        $query = File::query();

        $directions = $this->splitCsv($request->query('direction'));

        if (! empty($directions)) {
            $query->whereIn('type', $directions);
        }

        $purposes = $this->splitCsv($request->query('purpose'));

        if (! empty($purposes)) {
            $query->whereIn(
                DB::raw("split_part(file_path, '/', 2)"),
                $purposes,
            );
        }

        if ($from = $request->query('uploaded_at_from')) {
            $query->where('uploaded_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->query('uploaded_at_to')) {
            $query->where('uploaded_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($request->query('hide_expired') === '1') {
            $query->whereNull('expired_at');
        }

        $orderExpression = match ($sortColumn) {
            'purpose' => DB::raw("split_part(file_path, '/', 2)"),
            'status' => DB::raw('expired_at'),
            default => $sortColumn,
        };

        $query->orderBy($orderExpression, $sortDir);

        $total = (clone $query)->count();
        $rows = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $data = $rows->map(function (File $file) {
            $segments = explode('/', $file->file_path);
            $purpose = $segments[1] ?? '';

            return [
                'id' => $file->id,
                'type' => $file->type,
                'purpose' => $purpose,
                'original_filename' => $file->original_filename,
                'uploaded_at' => $file->uploaded_at?->toIso8601String(),
                'expired_at' => $file->expired_at?->toIso8601String(),
                'is_expired' => $file->expired_at !== null,
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function purposeOptions(): array
    {
        return File::query()
            ->selectRaw("DISTINCT split_part(file_path, '/', 2) AS purpose")
            ->orderBy('purpose')
            ->pluck('purpose')
            ->filter(fn (?string $p) => $p !== null && $p !== '')
            ->map(fn (string $p) => ['value' => $p, 'label' => $p])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function splitCsv(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $v) => $v !== '',
        ));
    }
}
