<?php

namespace Modules\Inventory\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Core\Contracts\Actions\Products\AdjustsStock;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\StockMovement;

/**
 * Gives the demo catalogue a stock ledger with some history in it, so the
 * movement list is not one opening balance per variant.
 *
 * Goes through Lunar's `AdjustStock`, which records the movement AND updates the
 * per-location level in one place — the same path a stock-take takes. Writing
 * movements directly would leave the ledger and the rollup disagreeing, which is
 * precisely the failure the ledger exists to expose.
 *
 * Everything is an `Adjustment`: Lunar reserves `shipped` and `returned` for
 * fulfilment transitions, which it makes itself, and inventing them here would
 * describe deliveries that never happened.
 */
class StockHistorySeeder extends Seeder
{
    public function run(): void
    {
        $adjust = app(AdjustsStock::class);

        // Anything beyond the opening balances the variant migration writes
        // means this has already run; the ledger is append-only, so a re-run
        // would stack duplicate history.
        $existing = StockMovement::query()->where('type', '!=', 'opening_balance')->exists();

        if ($existing) {
            $this->command?->info('Stock history already present — skipping.');

            return;
        }

        $variants = ProductVariant::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->limit(120)
            ->get();

        if ($variants->isEmpty()) {
            $this->command?->warn('No variants found — run ProductVariantMatrixSeeder first.');

            return;
        }

        $movements = 0;

        foreach ($variants as $i => $variant) {
            $adjust->execute($variant, 25, 'Nhập hàng đầu kỳ');
            $movements++;

            // A few sales, so the list is not all restocks and stock trends down.
            $sales = 1 + ($i % 3);

            for ($s = 0; $s < $sales; $s++) {
                if ((int) $variant->refresh()->stock_on_hand < 2) {
                    break;
                }

                $adjust->execute($variant, -1 - ($s % 2), 'Bán lẻ');
                $movements++;
            }

            // Occasional stock-take correction, the kind a shop actually records.
            if ($i % 5 === 0) {
                $adjust->execute($variant, -1, 'Kiểm kê: lệch tồn');
                $movements++;
            }
        }

        $this->command?->info("Recorded {$movements} stock movements across {$variants->count()} variants.");
    }
}
