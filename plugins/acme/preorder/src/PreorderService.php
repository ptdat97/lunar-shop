<?php

namespace Acme\Preorder;

use Acme\Preorder\Models\Preorder;
use Illuminate\Support\Facades\Cache;
use Lunar\Models\Product;
use Modules\Platform\Plugin\PluginSettings;

/**
 * Single source of pre-order state. Used by both the purchasable filter and the
 * product.resource enrichment, so "can pre-order" and "is shown as pre-order"
 * never disagree.
 */
class PreorderService
{
    /** Whether a product is currently pre-orderable. */
    public function isEnabled(int $productId): bool
    {
        return (bool) $this->for($productId)?->enabled;
    }

    /** @return array{enabled:bool, expected_at:?string}|null */
    public function badge(int $productId): ?array
    {
        $preorder = $this->for($productId);

        if (! $preorder || ! $preorder->enabled) {
            return null;
        }

        return [
            'enabled' => true,
            'expected_at' => $preorder->expected_at?->toDateString(),
            'label' => PluginSettings::for('acme/preorder')->get('label', 'Pre-order'),
        ];
    }

    public function enable(int $productId, ?string $expectedAt = null): Preorder
    {
        Cache::forget($this->key($productId));

        // Pre-order IS "always purchasable" in Lunar terms — flip the product's
        // variants to `always` so Lunar's own cart-stock validator (and the
        // Inventory oversell guard) permit buying while out of stock. This uses
        // Lunar's native backorder semantics rather than fighting its pipeline.
        Product::find($productId)?->variants()->update(['purchasable' => 'always']);

        return Preorder::updateOrCreate(
            ['product_id' => $productId],
            ['enabled' => true, 'expected_at' => $expectedAt],
        );
    }

    protected function for(int $productId): ?Preorder
    {
        return Cache::remember(
            $this->key($productId),
            now()->addMinutes(10),
            fn () => Preorder::where('product_id', $productId)->first(),
        );
    }

    protected function key(int $productId): string
    {
        return "preorder:{$productId}";
    }
}
