<?php

namespace Tests\Feature;

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Modules\Catalog\Strategies\CoPurchaseStrategy;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Recommendations API: product page + mini-cart. Both return the standard
 * ProductResource shape so the storefront card renderer reuses them.
 */
class RecommendationTest extends TestCase
{
    use CreatesStorefrontData;

    /** A paid order containing the given products' first variants. */
    private function paidOrderWith(array $products): Order
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'CO-'.uniqid(),
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ]);

        foreach ($products as $product) {
            $variant = $product->skus->first();
            OrderLine::factory()->create([
                'order_id' => $order->id,
                'purchasable_type' => $variant->getMorphClass(),
                'purchasable_id' => $variant->id,
                'type' => 'physical', 'description' => 'x', 'quantity' => 1,
                'unit_price' => 500, 'unit_quantity' => 1, 'sub_total' => 500,
                'discount_total' => 0, 'tax_total' => 0, 'total' => 500,
            ]);
        }

        return $order;
    }

    public function test_co_purchase_strategy_returns_products_bought_together(): void
    {
        $this->seedBaseData();
        $a = $this->createProduct(['name' => 'A']);
        $b = $this->createProduct(['name' => 'B']);
        $c = $this->createProduct(['name' => 'C']);

        // A+B in one order, A+C in another → co-purchase of A is {B, C}.
        $this->paidOrderWith([$a, $b]);
        $this->paidOrderWith([$a, $c]);

        $recs = app(CoPurchaseStrategy::class)->for($a->fresh(), 8);

        $ids = $recs->pluck('id')->all();
        $this->assertContains($b->id, $ids);
        $this->assertContains($c->id, $ids);
        $this->assertNotContains($a->id, $ids, 'source product is excluded');
    }

    public function test_co_purchase_ignores_unpaid_orders(): void
    {
        $this->seedBaseData();
        $a = $this->createProduct(['name' => 'A']);
        $b = $this->createProduct(['name' => 'B']);

        $order = $this->paidOrderWith([$a, $b]);
        $order->update(['status' => 'awaiting-payment']); // not a real purchase

        $recs = app(CoPurchaseStrategy::class)->for($a->fresh(), 8);

        $this->assertNotContains($b->id, $recs->pluck('id')->all());
    }

    public function test_product_recommendations_return_curated_association_first(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['name' => 'Source Tee']);
        $curated = $this->createProduct(['name' => 'Curated Match']);

        // Hand-picked cross-sell — should surface in recommendations.
        $product->associate($curated, 'cross-sell');

        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}/recommendations")
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'variants']]])
            ->assertJsonFragment(['id' => $curated->id]);
    }

    public function test_product_recommendations_404_for_unknown_slug(): void
    {
        $this->seedBaseData();

        $this->getJson('/api/v1/products/nope/recommendations')
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_cart_recommendations_exclude_products_in_cart(): void
    {
        $this->seedBaseData();

        $inCart = $this->createProduct(['name' => 'In Cart']);
        $curated = $this->createProduct(['name' => 'Suggested']);
        $inCart->associate($curated, 'cross-sell');

        // Put the source product in the cart.
        $this->postJson('/api/v1/cart', [
            'sku_id' => $inCart->skus->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $response = $this->getJson('/api/v1/cart/recommendations')
            ->assertOk()
            ->assertJsonStructure(['data' => []]);

        // The product already in the cart must not be recommended back.
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($inCart->id), 'cart product should be excluded');
    }
}
