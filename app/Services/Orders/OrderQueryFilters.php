<?php

namespace App\Services\Orders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared status + date-window filter resolution for the orders table and any
 * surface (e.g. bulk packing-slip print) that needs the same set of "what's
 * the user looking at right now" matches.
 */
class OrderQueryFilters
{
    public const DEFAULT_DATE_WINDOW = '90';

    public const FILTER_KEYS = ['status', 'date_window'];

    /**
     * @var list<array{value: string, label: string}>
     */
    public const DATE_WINDOWS = [
        ['value' => '30', 'label' => 'Last 30 Days'],
        ['value' => '60', 'label' => 'Last 60 Days'],
        ['value' => '90', 'label' => 'Last 90 Days'],
        ['value' => '365', 'label' => 'Last Year'],
        ['value' => '730', 'label' => 'Last 2 Years'],
        ['value' => 'all', 'label' => 'All Time'],
    ];

    public static function apply(Builder $query, Request $request): Builder
    {
        $statuses = self::splitCsv($request->query('status'));

        if ($statuses !== []) {
            $query->whereIn('tcgplayer_status', $statuses);
        }

        $window = self::resolveWindow($request->query('date_window'));

        if ($window !== null) {
            $query->where(
                'order_date',
                '>=',
                Carbon::now()->subDays($window)->startOfDay()->toDateString(),
            );
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public static function splitCsv(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $v) => $v !== '',
        ));
    }

    /**
     * Returns the day-count to apply, or null when no date filter should run
     * (i.e. the "All Time" preset). Falls back to the default window when the
     * input is absent or unrecognised so legacy URLs keep working.
     */
    private static function resolveWindow(mixed $raw): ?int
    {
        $value = is_string($raw) && $raw !== '' ? $raw : self::DEFAULT_DATE_WINDOW;

        if ($value === 'all') {
            return null;
        }

        $allowed = array_column(self::DATE_WINDOWS, 'value');

        if (! in_array($value, $allowed, true)) {
            $value = self::DEFAULT_DATE_WINDOW;
        }

        return (int) $value;
    }
}
