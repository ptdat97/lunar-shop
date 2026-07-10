<?php

namespace Modules\Catalog\Strategies;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Modules\Catalog\Contracts\RecommendationStrategy;
use Modules\Order\Support\OrderStatus;

/**
 * "Frequently bought together": products that appear in the same paid orders as
 * the source product, ranked by co-occurrence frequency. Automatic — derived
 * from real purchase history (lunar_order_lines), no curation needed.
 *
 * Sits between the curated AssociationStrategy (highest priority) and the
 * CollectionStrategy fallback. Cheap: one aggregate query, and the outer
 * RecommendationService caches the resolved ids.
 *
 * order_lines.purchasable is a ProductVariant → variant.product_id gives the
 * product; we join through variants so co-purchase is counted at product level.
 */
class CoPurchaseStrategy implements RecommendationStrategy
{
    /**
     * Statuses that count as a real purchase — one definition across the app.
     *
     * @return list<string>
     */
    protected function paidStatuses(): array
    {
        return OrderStatus::paid();
    }

    public function for(Product $product, int $limit = 8): Collection
    {
        // Orders (paid) that contain the source product.
        $orderIds = DB::table('lunar_order_lines as ol')
            ->join('lunar_product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'ol.purchasable_id')
                    ->where('ol.purchasable_type', '=', 'product_variant');
            })
            ->join('lunar_orders as o', 'o.id', '=', 'ol.order_id')
            ->where('pv.product_id', $product->id)
            ->whereIn('o.status', $this->paidStatuses())
            ->distinct()
            ->pluck('ol.order_id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        // Other products in those orders, ranked by how often they co-occur.
        $productIds = DB::table('lunar_order_lines as ol')
            ->join('lunar_product_variants as pv', function ($join) {
                $join->on('pv.id', '=', 'ol.purchasable_id')
                    ->where('ol.purchasable_type', '=', 'product_variant');
            })
            ->whereIn('ol.order_id', $orderIds)
            ->where('pv.product_id', '!=', $product->id)
            ->groupBy('pv.product_id')
            ->orderByRaw('COUNT(DISTINCT ol.order_id) DESC')
            ->limit($limit)
            ->pluck('pv.product_id');

        if ($productIds->isEmpty()) {
            return collect();
        }

        // Hydrate published products, preserving the co-occurrence order.
        $products = Product::query()
            ->where('status', 'published')
            ->whereIn('id', $productIds)
            ->with(['variants', 'thumbnail', 'brand'])
            ->get()
            ->keyBy('id');

        return $productIds
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->take($limit)
            ->values();
    }
}
