<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Product;
use Modules\Catalog\Models\SizeChartRow;
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

    /** @var array<int, Collection> customer id => their whole sized-order history (per request) */
    protected array $customerHistoryCache = [];

    /** @var array<int, int> product id => its size chart id, for products the customer bought (per request) */
    protected array $badgeProductChart = [];

    /** @var array<int, list<string>> size chart id => chart sizes, ascending (per request) */
    protected array $badgeChartSizes = [];

    /** Whether the badge chart map has been loaded for this request. */
    protected bool $badgeChartMapLoaded = false;

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
     * A grid-safe "your size" badge for one product, or null when there is
     * nothing trustworthy to show (docs/roadmap.md §14).
     *
     * This is the CONSERVATIVE cousin of {@see self::for()} — deliberately not
     * the deduction used on the product page:
     *
     *  - only a size the shopper actually bought and kept counts. A kept size
     *    is a fact; every "step one up/down" off a return is a prediction, and
     *    a wrong badge is wrong for a whole grid at once (the roadmap: badge
     *    đoán sai một lần là mất lòng tin vĩnh viễn, vì nó xuất hiện ở lưới).
     *  - the product page keeps the full signal for exactly those predictions
     *    (via {@see self::for()}) — there it has room to explain them.
     *
     * A whole grid costs a FLAT number of queries, not one per card: the
     * customer's history is loaded once per request, and the charts for the
     * products they bought with it — never per product on the page.
     */
    public function badge(Customer $customer, Product $product): ?string
    {
        // A shopper who never bought a sized product can never have a badge.
        // Guard BEFORE every other lookup, so a sign-in with no history costs
        // the single history query and nothing else on a whole page.
        $rows = $this->customerHistory($customer);

        if ($rows->isEmpty()) {
            return null;
        }

        // No history on THIS product → no badge. Also keeps the chart lookups
        // away from products the visitor never bought.
        $productRows = $rows->filter(fn ($row) => (int) $row->product_id === (int) $product->id);

        if ($productRows->isEmpty()) {
            return null;
        }

        if (! $product->relationLoaded('productOptions')) {
            $product->load('productOptions');
        }

        $sizeOptionId = $this->sizeOptionId($product);

        if ($sizeOptionId === null) {
            return null;
        }

        $sizes = $this->badgeChartSizes($product);

        if ($sizes === []) {
            return null;
        }

        $rank = array_flip($sizes);

        $kept = [];
        $returned = [];

        foreach ($this->aggregateSizes($productRows->filter(fn ($row) => (int) $row->product_option_id === $sizeOptionId,
        )) as $size => $direction) {
            if (! isset($rank[$size])) {
                continue;
            }

            if ($direction === null) {
                $kept[] = $size;
            } else {
                $returned[$size] = $direction;
            }
        }

        if ($kept === []) {
            return null;
        }

        usort($kept, fn ($a, $b) => $rank[$a] <=> $rank[$b]);

        $recommended = $this->deduce($sizes, $rank, $kept, $returned);

        // Only a size they own. `deduce()` also steps off returns; that result
        // stays on the product page (via for()), never on a grid.
        return ($recommended !== null && in_array($recommended, $kept, true)) ? $recommended : null;
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
     * The id of the product's Size option, if it has one.
     *
     * The size axis used to be found positionally in the product's own
     * `variables` blob. Variants carry shared `ProductOption`s now, so the axis
     * is an option row — matched on handle or on a name reading "size" in any
     * locale, the same tolerance as before.
     */
    protected function sizeOptionId(Product $product): ?int
    {
        foreach ($product->productOptions as $option) {
            if (strtolower((string) $option->handle) === 'size') {
                return (int) $option->id;
            }

            $names = $option->name;
            $names = is_iterable($names) ? $names : [$names];

            foreach ($names as $name) {
                if (strtolower(trim((string) $name)) === 'size') {
                    return (int) $option->id;
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
        $sizeOptionId = $this->sizeOptionId($product);

        if ($sizeOptionId === null) {
            return [];
        }

        // The purchased size comes off the variant's option values now, so it is
        // a join rather than a positional lookup resolved in PHP afterwards —
        // the label arrives with the row.
        $rows = DB::table('lunar_order_lines as ol')
            ->join('lunar_product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'ol.purchasable_id')
                    ->where('ol.purchasable_type', '=', 'product_variant');
            })
            ->join('lunar_orders as o', 'o.id', '=', 'ol.order_id')
            ->join('lunar_product_option_value_product_variant as pvv', 'pvv.variant_id', '=', 'pv.id')
            ->join('lunar_product_option_values as ov', function ($join) use ($sizeOptionId) {
                $join->on('ov.id', '=', 'pvv.value_id')
                    ->where('ov.product_option_id', '=', $sizeOptionId);
            })
            ->where('pv.product_id', $product->id)
            ->where('o.customer_id', $customer->id)
            ->whereRaw(OrderStatus::paidSql('o'))
            // A size return against this exact order line, if any.
            ->leftJoin('return_request_lines as rrl', 'rrl.order_line_id', '=', 'ol.id')
            ->leftJoin('return_requests as rr', function ($join) {
                $join->on('rr.id', '=', 'rrl.return_request_id')
                    ->whereIn('rr.reason', [self::REASON_TOO_SMALL, self::REASON_TOO_LARGE, self::REASON_WRONG_SIZE])
                    ->where('rr.status', '!=', ReturnStatus::REJECTED);
            })
            ->select('ov.name as size_name', 'rr.reason')
            ->get();

        return $this->aggregateSizes($rows);
    }

    /**
     * Aggregate order-line rows into the per-size fit signal shared by
     * {@see self::history()} and {@see self::badge()}: a size kept at least
     * once maps to null (it fits), otherwise the return direction — or the
     * direction-less legacy reason when returns contradict each other.
     *
     * @param  iterable<int, object>  $rows  rows with `size_name` + `reason`
     * @return array<string, ?string>
     */
    protected function aggregateSizes(iterable $rows): array
    {
        $bySize = [];

        foreach ($rows as $row) {
            $names = json_decode((string) $row->size_name, true);
            $size = is_array($names)
                ? ($names['en'] ?? $names[app()->getLocale()] ?? reset($names) ?: null)
                : (string) $row->size_name;

            if (! $size) {
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

    /**
     * Every sized order line this customer has ever paid for, in ONE query,
     * memoised per request. Rows carry the product and option they came from so
     * {@see self::badge()} can slice per product in memory — the reason a whole
     * grid never issues a history query of its own.
     *
     * The size-axis filter is deliberately left to the caller: filtering HERE
     * would need the option id before the rows exist, and the join without a
     * filter is still correct because the per-product option slice happens in
     * badge(). Row volume stays tiny (one row per option value per order line).
     *
     * @return Collection<int, object>
     */
    protected function customerHistory(Customer $customer): Collection
    {
        $id = (int) $customer->id;

        if (array_key_exists($id, $this->customerHistoryCache)) {
            return $this->customerHistoryCache[$id];
        }

        $this->customerHistoryCache[$id] = DB::table('lunar_order_lines as ol')
            ->join('lunar_product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'ol.purchasable_id')
                    ->where('ol.purchasable_type', '=', 'product_variant');
            })
            ->join('lunar_orders as o', 'o.id', '=', 'ol.order_id')
            ->join('lunar_product_option_value_product_variant as pvv', 'pvv.variant_id', '=', 'pv.id')
            ->join('lunar_product_option_values as ov', 'ov.id', '=', 'pvv.value_id')
            ->where('o.customer_id', $id)
            ->whereRaw(OrderStatus::paidSql('o'))
            // A size return against this exact order line, if any.
            ->leftJoin('return_request_lines as rrl', 'rrl.order_line_id', '=', 'ol.id')
            ->leftJoin('return_requests as rr', function ($join) {
                $join->on('rr.id', '=', 'rrl.return_request_id')
                    ->whereIn('rr.reason', [self::REASON_TOO_SMALL, self::REASON_TOO_LARGE, self::REASON_WRONG_SIZE])
                    ->where('rr.status', '!=', ReturnStatus::REJECTED);
            })
            ->select(
                'ov.name as size_name',
                'rr.reason',
                'pv.product_id as product_id',
                'ov.product_option_id as product_option_id',
            )
            ->get();

        return $this->customerHistoryCache[$id];
    }

    /**
     * Sizes a product's chart offers, ascending — answered from a per-request
     * map of the charts for everything this customer has bought, so reading one
     * card never queries per card.
     *
     * Charts are reusable (staff pick from a handful), so the map is small even
     * for a shopper with many orders.
     *
     * @return list<string>
     */
    protected function badgeChartSizes(Product $product): array
    {
        $this->ensureBadgeChartMap();

        $chartId = $this->badgeProductChart[(int) $product->id] ?? null;

        return $chartId !== null ? ($this->badgeChartSizes[$chartId] ?? []) : [];
    }

    /**
     * Load, once per request, the chart (and its rows) for every product the
     * current customer has ever bought: one pivot query + one rows query, flat
     * regardless of how many cards a page renders. Chart order = row sort, id
     * tie-break — the same order {@see self::chartSizes()} uses.
     */
    protected function ensureBadgeChartMap(): void
    {
        if ($this->badgeChartMapLoaded) {
            return;
        }

        $this->badgeChartMapLoaded = true;

        $productIds = collect($this->customerHistoryCache)
            ->flatMap(fn ($rows) => $rows->pluck('product_id'))
            ->all();

        if (empty($productIds)) {
            return;
        }

        // product → chart (first chart wins — the panel assigns one per product).
        $pivot = DB::table('product_size_chart')
            ->whereIn('product_id', $productIds)
            ->get();

        $chartIds = [];

        foreach ($pivot as $row) {
            $pid = (int) $row->product_id;
            $cid = (int) $row->size_chart_id;

            if ($cid && ! isset($this->badgeProductChart[$pid])) {
                $this->badgeProductChart[$pid] = $cid;
                $chartIds[] = $cid;
            }
        }

        if (empty($chartIds)) {
            return;
        }

        $rows = SizeChartRow::query()
            ->whereIn('size_chart_id', $chartIds)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $byChart = [];

        foreach ($rows as $row) {
            $byChart[(int) $row->size_chart_id][] = (string) $row->size;
        }

        foreach ($chartIds as $chartId) {
            $this->badgeChartSizes[$chartId] = $byChart[$chartId] ?? [];
        }
    }
}
