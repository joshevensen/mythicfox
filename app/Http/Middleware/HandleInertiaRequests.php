<?php

namespace App\Http\Middleware;

use App\Jobs\ImportOrdersJob;
use App\Jobs\ImportPricingCustomExportJob;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            ...($request->user() ? [
                'global_imports' => $this->globalImports($request),
                'catalog_products' => fn (): array => $this->catalogProducts(),
            ] : []),
        ];
    }

    /**
     * Products for the top-nav catalog dropdown, ordered by products.name.
     *
     * @return list<array{id: int, label: string}>
     */
    private function catalogProducts(): array
    {
        return Product::query()
            ->orderBy('name')
            ->get(['id', 'name', 'display_name'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'label' => $product->display_label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     catalog: array{in_flight: bool, last_result: mixed, upload_error: mixed},
     *     orders: array{in_flight: bool, last_result: mixed}
     * }
     */
    private function globalImports(Request $request): array
    {
        return [
            'catalog' => [
                'in_flight' => Cache::has(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY),
                'last_result' => Cache::get(ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY),
                'upload_error' => $request->session()->get('catalog_upload_error'),
            ],
            'orders' => [
                'in_flight' => Cache::has(ImportOrdersJob::IN_FLIGHT_CACHE_KEY),
                'last_result' => Cache::get(ImportOrdersJob::LAST_RESULT_CACHE_KEY),
            ],
        ];
    }
}
