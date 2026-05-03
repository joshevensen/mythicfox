<?php

namespace App\Services\Orders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared status + date-range filter resolution for the orders table and any
 * surface (e.g. bulk packing-slip print) that needs the same set of "what's
 * the user looking at right now" matches.
 */
class OrderQueryFilters
{
    public const DEFAULT_WINDOW_DAYS = 90;

    public const FILTER_KEYS = ['status', 'order_date_from', 'order_date_to'];

    public static function apply(Builder $query, Request $request): Builder
    {
        $statuses = self::splitCsv($request->query('status'));

        if ($statuses !== []) {
            $query->whereIn('tcgplayer_status', $statuses);
        }

        [$from, $to] = self::resolveDateRange($request);

        if ($from !== null) {
            $query->where('order_date', '>=', $from->toDateString());
        }

        if ($to !== null) {
            $query->where('order_date', '<=', $to->toDateString());
        }

        return $query;
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public static function resolveDateRange(Request $request): array
    {
        $rawFrom = $request->query('order_date_from');
        $rawTo = $request->query('order_date_to');

        // 90-day default applies only when neither bound is present. Once the
        // user touches the date range, URL state takes over.
        if ($rawFrom === null && $rawTo === null) {
            return [
                Carbon::now()->subDays(self::DEFAULT_WINDOW_DAYS)->startOfDay(),
                Carbon::now()->endOfDay(),
            ];
        }

        $from = is_string($rawFrom) && $rawFrom !== ''
            ? Carbon::parse($rawFrom)->startOfDay()
            : null;
        $to = is_string($rawTo) && $rawTo !== ''
            ? Carbon::parse($rawTo)->endOfDay()
            : null;

        return [$from, $to];
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
}
