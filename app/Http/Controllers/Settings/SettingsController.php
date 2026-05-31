<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshSellerStats;
use App\Models\File;
use App\Models\Product;
use App\Models\SellerStats;
use App\Models\Set;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const FILES_PER_PAGE = 20;

    public function show(Request $request): Response
    {
        $products = Product::query()
            ->with(['sets' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'base_price' => $product->base_price,
                'high_price' => $product->high_price,
                'market_offset' => $product->market_offset,
                'high_offset' => $product->high_offset,
                'sets' => $product->sets->map(fn (Set $set) => [
                    'id' => $set->id,
                    'name' => $set->name,
                    'base_price' => $set->base_price,
                    'high_price' => $set->high_price,
                    'market_offset' => $set->market_offset,
                    'high_offset' => $set->high_offset,
                    'overridden' => $set->base_price !== null
                        || $set->high_price !== null
                        || $set->market_offset !== null
                        || $set->high_offset !== null,
                ])->all(),
                'sets_count' => $product->sets->count(),
            ])->all();

        return Inertia::render('Settings', [
            'products' => $products,
            'files' => $this->loadFiles($request),
            'filePurposes' => $this->purposeOptions(),
            'sellerStats' => $this->loadSellerStats(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSellerStats(): array
    {
        $stats = SellerStats::query()->orderBy('id')->first();

        $status = $this->deriveSellerStatsStatus($stats);

        return [
            'rating' => $stats?->rating !== null ? (float) $stats->rating : null,
            'review_count' => $stats?->review_count,
            'feedback' => $stats?->feedback ?? [],
            'feedback_count' => is_array($stats?->feedback) ? count($stats->feedback) : 0,
            'scraped_at' => $stats?->scraped_at?->toIso8601String(),
            'last_attempt_at' => $stats?->last_attempt_at?->toIso8601String(),
            'last_error' => $stats?->last_error,
            'consecutive_failures' => (int) ($stats?->consecutive_failures ?? 0),
            'status' => $status,
            'raw' => $stats?->toArray() ?? [],
            'refreshing' => Cache::has(RefreshSellerStats::IN_FLIGHT_CACHE_KEY),
        ];
    }

    /**
     * @return array{key: string, label: string, message: ?string}
     */
    private function deriveSellerStatsStatus(?SellerStats $stats): array
    {
        $now = Carbon::now();
        $scrapedAt = $stats?->scraped_at;
        $lastAttemptAt = $stats?->last_attempt_at;
        $consecutiveFailures = (int) ($stats?->consecutive_failures ?? 0);

        if ($consecutiveFailures >= 3) {
            return [
                'key' => 'failed',
                'label' => "Failed {$consecutiveFailures} days in a row",
                'message' => 'Selectors may have changed. Check the storefront page for redesigns.',
            ];
        }

        $ageDays = $scrapedAt?->diffInDays($now);

        if (
            ($scrapedAt !== null && $ageDays >= 14)
            || ($scrapedAt === null && $lastAttemptAt !== null)
        ) {
            $lastGood = $scrapedAt?->toFormattedDateString() ?? 'never';

            return [
                'key' => 'hidden',
                'label' => 'Public section hidden',
                'message' => "The 'What buyers say' section is no longer rendering on the homepage. Last good scrape: {$lastGood}.",
            ];
        }

        if ($scrapedAt !== null && $ageDays >= 7) {
            $hidesIn = max(0, 14 - (int) $ageDays);

            return [
                'key' => 'stale',
                'label' => "Stale — homepage hides in {$hidesIn} days",
                'message' => null,
            ];
        }

        if ($consecutiveFailures === 0 && $scrapedAt !== null && $ageDays < 7) {
            return [
                'key' => 'healthy',
                'label' => 'Healthy',
                'message' => null,
            ];
        }

        return [
            'key' => 'unknown',
            'label' => 'No data yet',
            'message' => 'Scraper has not run yet.',
        ];
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
                \DB::raw("split_part(file_path, '/', 2)"),
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
            'purpose' => \DB::raw("split_part(file_path, '/', 2)"),
            'status' => \DB::raw('expired_at'),
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
