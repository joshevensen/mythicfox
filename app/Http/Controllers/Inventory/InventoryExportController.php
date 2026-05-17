<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Services\Catalog\InventoryRecomputeService;
use App\Services\Catalog\PricingExporter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    private const PREVIEW_PAGE_SIZE = 50;

    public function __construct(
        private readonly InventoryRecomputeService $recomputeService,
    ) {}

    /**
     * Step 1: recompute every inventory row's calculated_price. Never touches
     * override_price. Returns a summary the page uses to open the preview
     * modal.
     */
    public function recompute(): array
    {
        $result = $this->recomputeService->recompute();

        return [
            'rows_processed' => $result->rowsProcessed,
            'rows_with_result' => $result->rowsWithResult,
            'rows_null_result' => $result->rowsNullResult,
            'changed_count' => $this->changedCount(),
            'total_rows' => Inventory::query()->count(),
            'first_export' => $this->isFirstExport(),
        ];
    }

    /**
     * Step 2: paginated diff data for the preview modal. With `show_all` off
     * (default), returns only rows where the current effective price differs
     * from the last_exported_price.
     */
    public function preview(Request $request): array
    {
        $showAll = $request->query('show_all') === '1';
        $page = max(1, (int) $request->query('page', 1));
        $perPage = self::PREVIEW_PAGE_SIZE;

        $query = $this->diffQuery($showAll);

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('cards.product_name')
            ->orderBy('cards.number')
            ->orderBy('cards.condition')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get([
                'inventory.id as id',
                'inventory.last_exported_price as old_price',
                'inventory.calculated_price as calculated_price',
                'inventory.override_price as override_price',
                'cards.product_name as product_name',
                'cards.number as number',
                'cards.condition as condition',
                'sets.name as set_name',
            ]);

        $data = $rows->map(function ($row) {
            $newPrice = $row->override_price ?? $row->calculated_price;
            $delta = ($row->old_price !== null && $newPrice !== null)
                ? (int) $newPrice - (int) $row->old_price
                : null;

            return [
                'id' => (int) $row->id,
                'product_name' => (string) $row->product_name,
                'number' => (string) $row->number,
                'condition' => (string) $row->condition,
                'set_name' => (string) $row->set_name,
                'old_price' => $row->old_price === null ? null : (int) $row->old_price,
                'new_price' => $newPrice === null ? null : (int) $newPrice,
                'delta' => $delta,
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'changed_count' => $this->changedCount(),
                'total_rows' => Inventory::query()->count(),
                'first_export' => $this->isFirstExport(),
            ],
        ];
    }

    /**
     * Step 3: write the CSV via PricingExporter (which also updates
     * last_exported_price for every row inside its DB transaction), then
     * stream the file back to the browser as a download.
     */
    public function download(): StreamedResponse
    {
        $changedBefore = $this->changedCount();
        $totalBefore = Inventory::query()->count();

        $result = (new PricingExporter)->export();

        $path = $result->file->file_path;
        $filename = basename($path);

        return Storage::download($path, $filename, [
            'Content-Type' => 'text/csv',
            'X-Mf-Rows-Written' => (string) $result->rowsWritten,
            'X-Mf-Rows-Changed' => (string) $changedBefore,
            'X-Mf-Rows-Total' => (string) $totalBefore,
        ]);
    }

    private function diffQuery(bool $showAll): Builder
    {
        $query = DB::table('inventory')
            ->join('cards', 'cards.id', '=', 'inventory.card_id')
            ->join('sets', 'sets.id', '=', 'cards.set_id');

        if (! $showAll) {
            // "Changed" = current effective ≠ last_exported_price. The
            // IS DISTINCT FROM operator (PostgreSQL native) treats nulls as
            // distinct from non-null values, so first-export rows with
            // null last_exported_price count as changed regardless of their
            // current effective price.
            $query->whereRaw(
                'COALESCE(inventory.override_price, inventory.calculated_price) IS DISTINCT FROM inventory.last_exported_price'
            );
        }

        return $query;
    }

    private function changedCount(): int
    {
        return $this->diffQuery(showAll: false)->count();
    }

    private function isFirstExport(): bool
    {
        return ! Inventory::query()
            ->whereNotNull('last_exported_price')
            ->exists();
    }
}
