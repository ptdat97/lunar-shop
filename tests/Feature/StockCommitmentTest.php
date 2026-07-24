<?php

namespace Tests\Feature;

use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Services\StockLedger;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Stock commitment — "sold but not yet shipped" is tracked apart from what is
 * physically on the shelf (Bagisto's ordered_inventories idea, as one column).
 *
 * Before this, ordering decremented `quantity` outright, so the single number
 * answered "how many can I sell?" correctly and "how many are in the stockroom?"
 * wrongly. A shop counting stock could never reconcile with the system.
 *
 *   on-hand  = lunar_product_skus.quantity   (what a stock-take counts)
 *   committed= lunar_product_skus.committed  (of those, already sold)
 *   sellable = on-hand - committed           (getTotalInventory)
 */
class StockCommitmentTest extends TestCase
{
    use CreatesStorefrontData;

    private function order(ProductSku $sku, int $quantity): Order
    {
        $this->postJson('/api/v1/cart', ['sku_id' => $sku->id, 'quantity' => $quantity])->assertSuccessful();
        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        return Order::latest('id')->first();
    }

    public function test_an_order_holds_stock_without_taking_it_off_the_shelf(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $this->order($sku, 4);

        $fresh = $sku->fresh();
        $this->assertSame(10, (int) $fresh->quantity, 'the goods are still in the stockroom');
        $this->assertSame(4, (int) $fresh->committed);
        $this->assertSame(6, $fresh->getTotalInventory());
    }

    public function test_dispatch_is_when_stock_actually_leaves(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $order = $this->order($sku, 4);
        $order->update(['status' => OrderStatus::DISPATCHED]);

        $fresh = $sku->fresh();
        $this->assertSame(6, (int) $fresh->quantity);
        $this->assertSame(0, (int) $fresh->committed);
        $this->assertNotNull($order->fresh()->dispatched_at);
    }

    public function test_dispatching_twice_does_not_decrement_twice(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $order = $this->order($sku, 4);
        $order->update(['status' => OrderStatus::DISPATCHED]);
        // A retried webhook or a second click in admin.
        $order->fresh()->update(['status' => OrderStatus::DISPATCHED]);

        $this->assertSame(6, (int) $sku->fresh()->quantity, 'one shipment, one decrement');
    }

    public function test_cancelling_before_dispatch_frees_the_hold_without_inventing_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $order = $this->order($sku, 4);
        $order->update(['status' => OrderStatus::CANCELLED]);

        $fresh = $sku->fresh();
        // The units never left, so crediting `quantity` would create stock
        // out of nothing — the exact bug the split is meant to prevent.
        $this->assertSame(10, (int) $fresh->quantity);
        $this->assertSame(0, (int) $fresh->committed);
        $this->assertSame(10, $fresh->getTotalInventory());
    }

    public function test_cancelling_after_dispatch_puts_the_units_back_on_the_shelf(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $order = $this->order($sku, 4);
        $order->update(['status' => OrderStatus::DISPATCHED]);
        $this->assertSame(6, (int) $sku->fresh()->quantity);

        // A return: the goods physically come back.
        $order->fresh()->update(['status' => OrderStatus::REFUNDED]);

        $this->assertSame(10, (int) $sku->fresh()->quantity);
    }

    public function test_committed_units_cannot_be_sold_to_someone_else(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 2])->skus->first();

        $this->order($sku, 2);

        // The shelf still reads 2, but all of it is spoken for. A second shopper
        // must be refused — reading `quantity` instead of the sellable figure
        // here is exactly how a commitment model oversells.
        $this->assertSame(2, (int) $sku->fresh()->quantity);
        $this->postJson('/api/v1/cart', ['sku_id' => $sku->id, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_the_storefront_reports_a_fully_committed_sku_as_out_of_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 2])->skus->first();

        $this->order($sku, 2);

        $inventory = app(InventoryService::class);

        $this->assertSame(2, $inventory->onHand($sku->id));
        $this->assertSame(2, $inventory->committed($sku->id));
        $this->assertSame(0, $inventory->available($sku->id));
        $this->assertFalse(
            $inventory->hasPhysicalStock($sku->id),
            'showing committed units as buyable defers the oversell to checkout',
        );
    }

    public function test_uncommit_is_clamped_so_a_double_release_cannot_inflate_sellable_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();
        $ledger = app(StockLedger::class);

        $ledger->commit($sku->id, 3, 'order', 1);
        $ledger->uncommit($sku->id, 3, 1);
        // Second release of the same units (a retried cancel).
        $ledger->uncommit($sku->id, 3, 1);

        $fresh = $sku->fresh();
        $this->assertSame(0, (int) $fresh->committed, 'committed must not go negative');
        $this->assertSame(10, $fresh->getTotalInventory());
    }

    public function test_an_order_that_is_never_dispatched_is_flagged_as_holding_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();
        $inventory = app(InventoryService::class);

        $order = $this->order($sku, 2);
        $order->update(['status' => OrderStatus::PAID[0], 'placed_at' => now()]);

        // Fresh: nothing to warn about yet.
        $this->assertCount(0, $inventory->staleCommitments());

        // The shop forgot to mark it dispatched. Those units are now held with
        // nothing to free them — the failure mode the warning exists to catch.
        $order->fresh()->update([
            'placed_at' => now()->subDays(InventoryService::STALE_COMMITMENT_DAYS + 1),
        ]);

        $stale = $inventory->staleCommitments();
        $this->assertCount(1, $stale);
        $this->assertSame($order->id, $stale->first()->id);
    }

    public function test_a_dispatched_order_is_not_flagged(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $order = $this->order($sku, 2);
        $order->update([
            'status' => OrderStatus::DISPATCHED,
            'placed_at' => now()->subDays(30),
        ]);

        $this->assertCount(0, app(InventoryService::class)->staleCommitments());
    }

    public function test_the_ledger_records_the_hold_without_claiming_the_shelf_moved(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();

        $this->order($sku, 3);

        $movement = StockMovement::where('product_sku_id', $sku->id)
            ->latest('id')->first();

        // before == after: a stock-take at that moment would still have counted
        // 10. The hold is recorded in meta, not by faking a shelf movement.
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(10, $movement->stock_after);
        $this->assertSame(0, $movement->meta['committed_before']);
        $this->assertSame(3, $movement->meta['committed_after']);
    }
}
