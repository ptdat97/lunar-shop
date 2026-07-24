<?php

namespace Tests\Feature;

use Illuminate\Validation\ValidationException;
use Modules\Catalog\Services\SkuBuilderService;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStockAdjustmentException;
use Modules\Inventory\Services\StockLedger;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * `committed` must never exceed `quantity`.
 *
 * Committed units are goods already sold and awaiting dispatch. Letting the
 * shelf fall below that number describes a shop that has promised stock it
 * cannot ship — and nothing downstream flags it, because sellable
 * (`quantity - committed`) clamps at 0 either way. So both write paths refuse:
 * the manual ledger adjustment and the product editor's SKU rewrite.
 */
class StockCommitmentIntegrityTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_setting_stock_below_committed_is_refused(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();
        $sku->update(['committed' => 3]);

        $this->expectException(InvalidStockAdjustmentException::class);

        app(StockLedger::class)->set($sku->id, 1, StockMovementType::Manual, 'stocktake');
    }

    public function test_a_refused_adjustment_leaves_stock_untouched(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();
        $sku->update(['committed' => 3]);

        try {
            app(StockLedger::class)->set($sku->id, 1, StockMovementType::Manual, 'stocktake');
        } catch (InvalidStockAdjustmentException) {
            // expected
        }

        $fresh = $sku->fresh();
        $this->assertSame(10, (int) $fresh->quantity, 'a refused adjustment must not write');
        $this->assertSame(3, (int) $fresh->committed);
    }

    public function test_setting_stock_exactly_to_committed_is_allowed(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();
        $sku->update(['committed' => 3]);

        // Every remaining unit is spoken for — valid, just nothing left to sell.
        app(StockLedger::class)->set($sku->id, 3, StockMovementType::Manual, 'stocktake');

        $fresh = $sku->fresh();
        $this->assertSame(3, (int) $fresh->quantity);
        $this->assertSame(0, $fresh->getTotalInventory());
    }

    public function test_the_exception_reports_the_committed_figure(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 10])->skus->first();
        $sku->update(['committed' => 3]);

        try {
            app(StockLedger::class)->set($sku->id, 1, StockMovementType::Manual, 'stocktake');
            $this->fail('expected the adjustment to be refused');
        } catch (InvalidStockAdjustmentException $e) {
            // The admin notice picks its wording off this, so "below zero" and
            // "below committed" must stay distinguishable.
            $this->assertSame(3, $e->committed);
            $this->assertStringContainsString('committed', $e->getMessage());
        }
    }

    public function test_a_negative_adjustment_still_reports_no_committed_figure(): void
    {
        $this->seedBaseData();
        $sku = $this->createProduct(['stock' => 2])->skus->first();

        try {
            app(StockLedger::class)->adjust($sku->id, -5, StockMovementType::Adjustment, 'damage');
            $this->fail('expected the adjustment to be refused');
        } catch (InvalidStockAdjustmentException $e) {
            $this->assertNull($e->committed, 'a below-zero refusal is a different mistake');
        }
    }

    public function test_the_product_editor_carries_committed_across_a_sku_rewrite(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 10]);
        $sku = $product->skus->first();
        $sku->update(['committed' => 4]);

        // SkuBuilderService delete-and-recreates the SKU rows. `committed` is
        // not in the editor payload, so it has to be carried by sku code — or
        // saving the variant tab silently frees every hold and puts sold stock
        // back on sale.
        app(SkuBuilderService::class)->save(
            $product->fresh(),
            $product->variables ?? [],
            [['sku' => $sku->sku, 'price' => 1999, 'quantity' => 10, 'status' => 'published']],
        );

        $rebuilt = $product->fresh()->skus()->where('sku', $sku->sku)->first();
        $this->assertSame(4, (int) $rebuilt->committed, 'holds must survive an editor save');
        $this->assertSame(6, $rebuilt->getTotalInventory());
    }

    public function test_the_product_editor_refuses_stock_below_committed(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 10]);
        $sku = $product->skus->first();
        $sku->update(['committed' => 4]);

        $this->expectException(ValidationException::class);

        app(SkuBuilderService::class)->save(
            $product->fresh(),
            $product->variables ?? [],
            [['sku' => $sku->sku, 'price' => 1999, 'quantity' => 1, 'status' => 'published']],
        );
    }

    public function test_the_editor_can_still_lower_stock_above_the_committed_line(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 10]);
        $sku = $product->skus->first();
        $sku->update(['committed' => 4]);

        app(SkuBuilderService::class)->save(
            $product->fresh(),
            $product->variables ?? [],
            [['sku' => $sku->sku, 'price' => 1999, 'quantity' => 5, 'status' => 'published']],
        );

        $rebuilt = $product->fresh()->skus()->where('sku', $sku->sku)->first();
        $this->assertSame(5, (int) $rebuilt->quantity);
        $this->assertSame(1, $rebuilt->getTotalInventory());
    }
}
