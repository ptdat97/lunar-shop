<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Customer;
use Lunar\Models\Product;
use Modules\Order\Support\OrderStatus;
use Modules\Order\Support\ReturnStatus;

/**
 * Size Intelligence v2: infer a shopper's true size from what they actually kept
 * versus what they sent back, and warn when they sit between two sizes.
 *
 * Two signals, both read from data the app already records:
 *  - a paid order line whose variant carries a Size option = a size they bought;
 *  - a return request against that line with a size reason = it did not fit, and
 *    the reason encodes the direction (too small / too large).
 *
 * Sizes are compared on the product's size chart ordering (SizeChartRow.sort),
 * not alphabetically, so "S < M < L" holds for any label scheme the chart uses.
 */
class FitHistoryService
{
    /** Return reasons that mean "the size was wrong", mapped to a direction. */
    public const REASON_TOO_SMALL = 'too-small';

    public const REASON_TOO_LARGE = 'too-large';

    /** Legacy reason from before the split: wrong size, direction unknown. */
    public const REASON_WRONG_SIZE = 'wrong-size';

    public function __construct(
        protected SizeChartService $charts,
    ) {}

    /**
     * Fit signal for a customer on a product, or null when there is nothing to
     * say (no chart, or no size history to learn from).
     *
     * @return null|array{
     *     recommended: ?string,
     *     kept: list<string>,
     *     returned: array<string, string>,
     *     between: ?array{0:string,1:string},
     *     advice: ?string
     * }
     */
    public function for(Customer $customer, Product $product): ?array
    {
        $sizes = $this->chartSizes($product);

        if ($sizes === []) {
            return null;
        }

        $history = $this->history($customer, $product);

        if ($history === []) {
            return null;
        }

        // Keep only sizes this product's chart actually offers, in chart order.
        $rank = array_flip($sizes);

        $kept = [];
        $returned = [];

        foreach ($history as $size => $direction) {
            if (! isset($rank[$size])) {
                continue;
            }

            if ($direction === null) {
                $kept[] = $size;
            } else {
                $returned[$size] = $direction;
            }
        }

        if ($kept === [] && $returned === []) {
            return null;
        }

        usort($kept, fn ($a, $b) => $rank[$a] <=> $rank[$b]);

        $recommended = $this->deduce($sizes, $rank, $kept, $returned);
        $between = $this->between($sizes, $rank, $returned);

        return [
            'recommended' => $recommended,
            'kept' => $kept,
            'returned' => $returned,
            'between' => $between,
            'advice' => $this->advice($recommended, $between),
        ];
    }

    /**
     * The size to suggest. A kept size is the strongest evidence — prefer the
     * most recent one. Otherwise step off a returned size in the direction the
     * customer told us (returned "too small" ⇒ go one size up).
     *
     * @param  list<string>  $sizes  chart sizes, ascending
     * @param  array<string,int>  $rank
     * @param  list<string>  $kept
     * @param  array<string,string>  $returned
     */
    protected function deduce(array $sizes, array $rank, array $kept, array $returned): ?string
    {
        // A size they kept and never returned is the answer.
        foreach ($kept as $size) {
            if (! isset($returned[$size])) {
                return $size;
            }
        }

        if ($returned === []) {
            return null;
        }

        // Step off the returned sizes. "too-small" pushes up, "too-large" pushes
        // down; a size returned in both directions cancels out and yields null.
        $candidates = [];

        foreach ($returned as $size => $direction) {
            $step = match ($direction) {
                self::REASON_TOO_SMALL => 1,
                self::REASON_TOO_LARGE => -1,
                default => null, // legacy wrong-size: no direction to act on
            };

            if ($step === null) {
                continue;
            }

            $next = $rank[$size] + $step;

            if (isset($sizes[$next]) && ! isset($returned[$sizes[$next]])) {
                $candidates[$sizes[$next]] = true;
            }
        }

        // Ambiguous (they'd need two different sizes at once) → say nothing.
        return count($candidates) === 1 ? array_key_first($candidates) : null;
    }

    /**
     * Between two sizes: they returned one size for being too small AND the very
     * next size up for being too large (or vice versa) — nothing in the chart
     * fits, so warn rather than recommend.
     *
     * @param  list<string>  $sizes
     * @param  array<string,int>  $rank
     * @param  array<string,string>  $returned
     * @return ?array{0:string,1:string}
     */
    protected function between(array $sizes, array $rank, array $returned): ?array
    {
        foreach ($returned as $size => $direction) {
            if ($direction !== self::REASON_TOO_SMALL) {
                continue;
            }

            $upper = $sizes[$rank[$size] + 1] ?? null;

            if ($upper !== null && ($returned[$upper] ?? null) === self::REASON_TOO_LARGE) {
                return [$size, $upper];
            }
        }

        return null;
    }

    /**
     * A translation key describing the signal, resolved by the caller so the
     * service stays free of presentation concerns.
     */
    protected function advice(?string $recommended, ?array $between): ?string
    {
        return match (true) {
            $between !== null => 'between_sizes',
            $recommended !== null => 'usual_size',
            default => null,
        };
    }

    /**
     * Sizes offered by the product's chart, ascending (chart order, not alpha).
     *
     * @return list<string>
     */
    protected function chartSizes(Product $product): array
    {
        $chart = $this->charts->chartFor($product);

        if (! $chart) {
            return [];
        }

        // Chart order drives every "one size up/down" step below, so tie-break
        // on id: `sort` defaults to 0 and charts built without explicit sorting
        // would otherwise have no defined order.
        return $chart->rows
            ->sortBy(fn ($row) => [$row->sort, $row->id])
            ->pluck('size')
            ->map(fn ($s) => (string) $s)
            ->values()
            ->all();
    }

    /**
     * The index of the product's "Size" variable within its flexible
     * `variables` definition, or null when the product has no size axis. A
     * variable is the size axis when its localised name equals "size"
     * (case-insensitive, any locale) — the same handle the old option carried.
     */
    protected function sizeAxisIndex(Product $product): ?int
    {
        foreach ($product->variables ?? [] as $i => $variable) {
            $names = $variable['name'] ?? [];
            $names = is_array($names) ? $names : [$names];

            foreach ($names as $name) {
                if (strtolower(trim((string) $name)) === 'size') {
                    return (int) $i;
                }
            }
        }

        return null;
    }

    /**
     * Every size this customer bought of this product, mapped to the direction it
     * failed (`too-small` / `too-large` / `wrong-size`), or null when they kept it.
     *
     * A size returned in one order but kept in another counts as kept: keeping is
     * the stronger signal (they returned it once, then found it right).
     *
     * @return array<string, ?string>
     */
    protected function history(Customer $customer, Product $product): array
    {
        // The purchased size now lives positionally in the SKU's `variants`
        // index into the product's flexible `variables` (no option-value pivot
        // to join). Pull the raw order lines + return reason per SKU, then
        // resolve each SKU's Size label in PHP against the product definition.
        $rows = DB::table('lunar_order_lines as ol')
            ->join('lunar_product_skus as ps', function ($join) {
                $join->on('ps.id', '=', 'ol.purchasable_id')
                    ->where('ol.purchasable_type', '=', 'product_sku');
            })
            ->join('lunar_orders as o', 'o.id', '=', 'ol.order_id')
            ->where('ps.product_id', $product->id)
            ->where('o.customer_id', $customer->id)
            ->whereIn('o.status', OrderStatus::paid())
            // A size return against this exact order line, if any.
            ->leftJoin('return_request_lines as rrl', 'rrl.order_line_id', '=', 'ol.id')
            ->leftJoin('return_requests as rr', function ($join) {
                $join->on('rr.id', '=', 'rrl.return_request_id')
                    ->whereIn('rr.reason', [self::REASON_TOO_SMALL, self::REASON_TOO_LARGE, self::REASON_WRONG_SIZE])
                    ->where('rr.status', '!=', ReturnStatus::REJECTED);
            })
            ->select('ps.variants as variant_indexes', 'rr.reason')
            ->get();

        // Which variable in this product is the Size axis (handle 'size', or a
        // name equal to "size" in any locale)? Its index selects the size label.
        $sizeAxis = $this->sizeAxisIndex($product);

        if ($sizeAxis === null) {
            return [];
        }

        $variables = $product->variables ?? [];

        // Aggregate per size label: kept once ⇒ kept; else the (possibly
        // conflicting) return direction.
        $bySize = [];

        foreach ($rows as $row) {
            $indexes = json_decode((string) $row->variant_indexes, true) ?: [];
            $valueIndex = $indexes[$sizeAxis] ?? null;
            if ($valueIndex === null) {
                continue;
            }

            $size = $variables[$sizeAxis]['values'][$valueIndex]['name']['en']
                ?? $variables[$sizeAxis]['values'][$valueIndex]['name'][app()->getLocale()]
                ?? null;
            if ($size === null) {
                continue;
            }

            $bySize[$size] ??= ['reasons' => [], 'kept' => false];
            if ($row->reason === null) {
                $bySize[$size]['kept'] = true;
            } else {
                $bySize[$size]['reasons'][$row->reason] = true;
            }
        }

        $out = [];

        foreach ($bySize as $size => $info) {
            if ($info['kept']) {
                $out[(string) $size] = null; // kept at least once ⇒ it fits

                continue;
            }

            // Returned every time. Conflicting directions (too-small AND
            // too-large on the same size) carry no usable direction, so they
            // degrade to the direction-less reason rather than to "kept".
            $out[(string) $size] = count($info['reasons']) > 1
                ? self::REASON_WRONG_SIZE
                : (string) array_key_first($info['reasons']);
        }

        return $out;
    }
}
