<?php

namespace Tests\Feature;

use Lunar\Core\Models\Order;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Stock commitment — "sold but not yet shipped" is tracked apart from what is
 * physically on the shelf (Bagisto's ordered_inventories idea, as one column).
 *
 * Before this, ordering decremented `quantity` outright, so the single number
 * answered "how many can I sell?" correctly and "how many are in the stockroom?"
 * wrongly. A shop counting stock could never reconcile with the system.
 *
 *   on-hand   = stock_on_hand     (what a stock-take counts)
 *   committed = stock_committed   (of those, already sold)
 *   sellable  = stock_available   (getTotalInventory)
 *
 * This module used to implement all of that. Lunar 2.0 does it now, wired to the
 * order lifecycle: `OrderPlaced` commits, `OrderCancelled` releases, and a
 * fulfilment reaching a shipped state records the movement that finally takes
 * the units off the shelf.
 *
 * These tests were kept and pointed at the new mechanism rather than deleted
 * along with the code they used to cover. Delegating a guarantee to a dependency
 * is not the same as no longer needing it: these are the promises the shop makes
 * to a shopper, and exactly what would break silently on a Lunar upgrade. They
 * assert OUTCOMES through checkout, not the internals of whoever produces them.
 */
class StockCommitmentTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private function order(ProductVariant $sku, int $quantity): Order
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
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $this->order($sku, 4);

        $fresh = $sku->fresh();
        $this->assertSame(10, (int) $fresh->stock_on_hand, 'the goods are still in the stockroom');
        $this->assertSame(4, (int) $fresh->stock_committed);
        $this->assertSame(6, $fresh->getTotalInventory());
    }

    public function test_dispatch_is_when_stock_actually_leaves(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $order = $this->order($sku, 4);
        $this->moveOrderTo($order, OrderStatus::DISPATCHED);

        $fresh = $sku->fresh();
        $this->assertSame(6, (int) $fresh->stock_on_hand);
        $this->assertSame(0, (int) $fresh->stock_committed);

        // "When did it ship?" is per-fulfilment now (`shipped_at`), not a column
        // on the order: an order can ship in more than one parcel, and one
        // timestamp could never say that honestly.
        $this->assertNotNull($order->fresh()->fulfilments()->first()?->shipped_at);
    }

    public function test_dispatching_twice_does_not_decrement_twice(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $order = $this->order($sku, 4);
        $this->moveOrderTo($order, OrderStatus::DISPATCHED);
        // A retried webhook or a second click in admin.
        $this->moveOrderTo($order->fresh(), OrderStatus::DISPATCHED);

        $this->assertSame(6, (int) $sku->fresh()->stock_on_hand, 'one shipment, one decrement');
    }

    public function test_cancelling_before_dispatch_frees_the_hold_without_inventing_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $order = $this->order($sku, 4);
        $this->moveOrderTo($order, OrderStatus::CANCELLED);

        $fresh = $sku->fresh();
        // The units never left, so crediting `quantity` would create stock
        // out of nothing — the exact bug the split is meant to prevent.
        $this->assertSame(10, (int) $fresh->stock_on_hand);
        $this->assertSame(0, (int) $fresh->stock_committed);
        $this->assertSame(10, $fresh->getTotalInventory());
    }

    /**
     * Refunding does NOT restock, and that is the point.
     *
     * The old model conflated the two: any refund put the units back. Lunar 2.0
     * separates money from goods — stock returns when the FULFILMENT is marked
     * returned, because that is when the parcel is actually back. A refunded
     * order whose goods are still with the customer must not inflate the shelf.
     */
    public function test_refunding_does_not_restock_but_marking_the_return_does(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $order = $this->order($sku, 4);
        $this->moveOrderTo($order, OrderStatus::DISPATCHED);
        $this->assertSame(6, (int) $sku->fresh()->stock_on_hand);

        $this->moveOrderTo($order->fresh(), OrderStatus::REFUNDED);
        $this->assertSame(6, (int) $sku->fresh()->stock_on_hand, 'money came back, the goods did not');

        // The parcel actually arrives back.
        $order->fresh()->fulfilments()->first()->markReturned(notify: false);

        $this->assertSame(10, (int) $sku->fresh()->stock_on_hand);
    }

    public function test_committed_units_cannot_be_sold_to_someone_else(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 2])->variants->first();

        $this->order($sku, 2);

        // The shelf still reads 2, but all of it is spoken for. A second shopper
        // must be refused — reading `quantity` instead of the sellable figure
        // here is exactly how a commitment model oversells.
        $this->assertSame(2, (int) $sku->fresh()->stock_on_hand);
        $this->postJson('/api/v1/cart', ['sku_id' => $sku->id, 'quantity' => 1])
            ->assertStatus(422);
    }

    public function test_the_storefront_reports_a_fully_committed_sku_as_out_of_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 2])->variants->first();

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

    /**
     * A double release cannot inflate sellable stock — and now it cannot even be
     * expressed.
     *
     * The old model kept `committed` as a counter that code incremented and
     * decremented, so releasing twice would have driven it negative and invented
     * stock; a clamp existed to stop that. Lunar 2.0 RECOMPUTES committed from
     * the order book every time, so there is no counter to double-release. The
     * clamp is gone because the failure mode is.
     */
    public function test_releasing_twice_cannot_invent_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $order = $this->order($sku, 4);
        $this->assertSame(4, (int) $sku->fresh()->stock_committed);

        $this->moveOrderTo($order, OrderStatus::CANCELLED);
        $this->assertSame(0, (int) $sku->fresh()->stock_committed);

        // Recompute again from the same order book: idempotent by construction.
        $sku->fresh()->syncStockCommitment();

        $fresh = $sku->fresh();
        $this->assertSame(0, (int) $fresh->stock_committed);
        $this->assertSame(10, (int) $fresh->stock_on_hand, 'nothing was invented');
    }

    public function test_an_order_that_is_never_dispatched_is_flagged_as_holding_stock(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();
        $inventory = app(InventoryService::class);

        $order = $this->order($sku, 2);
        // A COD sale: placed and owed, nothing dispatched yet.
        $order->update($this->orderAttributesFor(OrderStatus::PAYMENT_OFFLINE));

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
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $order = $this->order($sku, 2);
        $this->moveOrderTo($order, OrderStatus::DISPATCHED);
        $order->update(['placed_at' => now()->subDays(30)]);

        $this->assertCount(0, app(InventoryService::class)->staleCommitments());
    }

    /**
     * Holding stock writes NO movement — and that is the correct ledger.
     *
     * The old ledger recorded a `sale` row at order creation with
     * `stock_before == stock_after`, plus the commitment in meta: a movement
     * entry describing something that did not move. Lunar's ledger holds
     * physical movements only, so `sum(movements) == on_hand` is an invariant
     * rather than an aspiration, and the commitment lives where it is derived
     * from — the order book.
     */
    public function test_holding_stock_records_no_movement(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->variants->first();

        $before = StockMovement::where('product_variant_id', $sku->id)->count();

        $this->order($sku, 3);

        $fresh = $sku->fresh();

        $this->assertSame(
            $before,
            StockMovement::where('product_variant_id', $sku->id)->count(),
            'a commitment is not a movement',
        );
        $this->assertSame(10, (int) $fresh->stock_on_hand, 'a stock-take would still count 10');
        $this->assertSame(3, (int) $fresh->stock_committed);
        $this->assertSame(7, $fresh->getTotalInventory());
    }
}
