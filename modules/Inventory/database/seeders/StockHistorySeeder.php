<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockLedger;

/**
 * Gives the demo SKUs a plausible stock history so the Inventory module has
 * something to show: the movement ledger on a SKU, the low-stock report, and
 * the stock-level columns in admin.
 *
 * Goes through StockLedger, which is the ONLY writer allowed to touch stock —
 * it writes the level and the ledger row in one transaction, so the running
 * `stock_before`/`stock_after` chain stays consistent. Writing stock_movements
 * rows directly here would produce a ledger that disagrees with the SKU's
 * actual quantity.
 *
 * Not idempotent in the strict sense — a ledger is append-only, so re-running
 * adds more history. It exits early if movements already exist, so the common
 * `db:seed` re-run stays a no-op.
 */
class StockHistorySeeder extends Seeder
{
    public function run(): void
    {
        // The ledger is append-only: re-running would stack duplicate history
        // onto SKUs that already have it. Checked first so a re-run costs one
        // query instead of loading every SKU.
        if (StockMovement::query()->exists()) {
            $this->command?->info('Stock movements already present — skipping.');

            return;
        }

        $ledger = app(StockLedger::class);

        $skus = ProductSku::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->limit(120)
            ->get();

        if ($skus->isEmpty()) {
            $this->command?->warn('No SKUs found — run ProductSkuMatrixSeeder first.');

            return;
        }

        $movements = 0;

        foreach ($skus as $i => $sku) {
            // Opening restock: the initial buy-in that put the SKU on the shelf.
            $ledger->adjust(
                skuId: $sku->id,
                delta: 25,
                type: StockMovementType::Restock,
                reason: 'Nhập hàng đầu kỳ',
            );
            $movements++;

            // A few sales, so the movement list is not all restocks and the
            // quantity trends down from the opening level.
            $sales = 1 + ($i % 3);
            for ($s = 0; $s < $sales; $s++) {
                // Never sell below zero — adjust() refuses a negative result.
                if ($sku->fresh()->quantity < 2) {
                    break;
                }

                $ledger->adjust(
                    skuId: $sku->id,
                    delta: -1 - ($s % 2),
                    type: StockMovementType::Sale,
                    reason: 'Bán lẻ',
                );
                $movements++;
            }

            // Occasional stock-take correction, the kind a shop actually records.
            if ($i % 5 === 0) {
                $ledger->adjust(
                    skuId: $sku->id,
                    delta: -1,
                    type: StockMovementType::Adjustment,
                    reason: 'Kiểm kê: lệch tồn',
                );
                $movements++;
            }
        }

        $this->command?->info("Recorded {$movements} stock movements across {$skus->count()} SKUs.");
    }
}
