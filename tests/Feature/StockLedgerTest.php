<?php

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStockAdjustmentException;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockLedger;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The stock ledger records every change to a variant's stock — sales,
 * releases, manual adjustments and editor edits — with the signed delta and
 * before/after levels. The ledger's stock_after must always equal the variant's
 * real stock (mutation check §17.2): if a movement's after diverges from the
 * DB, the ledger is lying.
 */
class StockLedgerTest extends TestCase
{
    use CreatesStorefrontData;

    private function placeOrder(ProductSku $sku, int $quantity): TestResponse
    {
        $this->postJson('/api/v1/cart', ['sku_id' => $sku->id, 'quantity' => $quantity]);
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        return $this->postJson('/api/v1/checkout', ['payment_type' => 'cod']);
    }

    public function test_placing_an_order_records_a_sale_movement(): void
    {
        $product = $this->createProduct(['stock' => 10]);
        $variant = $product->skus->first();

        $this->placeOrder($variant, 3)->assertSuccessful();

        $movement = StockMovement::where('product_sku_id', $variant->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertSame(StockMovementType::Sale, $movement->type);
        $this->assertSame(-3, $movement->quantity);

        // Ordering commits stock rather than shipping it, so the shelf count is
        // unchanged on both sides — a faithful record of what a stock-take would
        // have counted. The hold shows up in the movement's meta instead.
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(10, $movement->stock_after);
        $this->assertSame(3, $movement->meta['committed_after']);

        $fresh = $variant->fresh();
        $this->assertSame(10, (int) $fresh->quantity);
        $this->assertSame(7, $fresh->getTotalInventory());
    }

    public function test_cancelling_an_order_records_a_release_movement(): void
    {
        $product = $this->createProduct(['stock' => 10]);
        $variant = $product->skus->first();

        $this->placeOrder($variant, 4)->assertSuccessful();
        $this->assertSame(6, $variant->fresh()->getTotalInventory(), 'held units are not sellable');

        $order = Order::latest('id')->first();
        $order->update(['status' => OrderStatus::CANCELLED]);

        $release = StockMovement::where('product_sku_id', $variant->id)
            ->where('type', StockMovementType::Release->value)
            ->latest('id')->first();

        $this->assertNotNull($release);
        $this->assertSame(4, $release->quantity);

        $fresh = $variant->fresh();
        // Cancelled before dispatch: the units never left the shelf, so only the
        // hold unwinds. Crediting `quantity` here would invent stock.
        $this->assertSame(10, (int) $fresh->quantity);
        $this->assertSame(0, (int) $fresh->committed);
        $this->assertSame(10, $fresh->getTotalInventory());
        // Mutation check: the release entry's after matches the real shelf count.
        $this->assertSame((int) $fresh->quantity, $release->stock_after);
    }

    public function test_adjust_changes_stock_and_records_before_after(): void
    {
        $product = $this->createProduct(['stock' => 5]);
        $variant = $product->skus->first();

        $movement = app(StockLedger::class)->adjust(
            $variant->id, 8, StockMovementType::Restock, 'restock'
        );

        $this->assertSame(13, (int) $variant->fresh()->quantity);
        $this->assertSame(8, $movement->quantity);
        $this->assertSame(5, $movement->stock_before);
        $this->assertSame(13, $movement->stock_after);
        $this->assertSame($movement->stock_after, (int) $variant->fresh()->quantity);
    }

    public function test_set_records_a_signed_delta(): void
    {
        $product = $this->createProduct(['stock' => 10]);
        $variant = $product->skus->first();

        $movement = app(StockLedger::class)->set($variant->id, 3, StockMovementType::Manual, 'stocktake');

        $this->assertSame(3, (int) $variant->fresh()->quantity);
        $this->assertSame(-7, $movement->quantity);
        $this->assertSame(3, $movement->stock_after);
    }

    public function test_a_negative_adjustment_is_refused_and_leaves_stock_untouched(): void
    {
        $product = $this->createProduct(['stock' => 2]);
        $variant = $product->skus->first();

        try {
            app(StockLedger::class)->adjust($variant->id, -5, StockMovementType::Adjustment, 'damage');
            $this->fail('Expected InvalidStockAdjustmentException');
        } catch (InvalidStockAdjustmentException) {
            // expected
        }

        $this->assertSame(2, (int) $variant->fresh()->quantity);
        $this->assertDatabaseMissing('stock_movements', [
            'product_sku_id' => $variant->id,
            'type' => StockMovementType::Adjustment->value,
        ]);
    }

    public function test_editing_stock_via_eloquent_records_a_single_edit_movement(): void
    {
        $product = $this->createProduct(['stock' => 4]);
        $variant = $product->skus->first();

        $variant->update(['quantity' => 9]);

        $edits = StockMovement::where('product_sku_id', $variant->id)
            ->where('type', StockMovementType::Edit->value)->get();

        // Exactly one — the observer fires once, and it does not double-count
        // with any query-builder path.
        $this->assertCount(1, $edits);
        $this->assertSame(5, $edits->first()->quantity);
        $this->assertSame(4, $edits->first()->stock_before);
        $this->assertSame(9, $edits->first()->stock_after);
    }
}
