<?php

namespace Modules\Inventory\Enums;

/**
 * The kinds of stock movement recorded in the ledger.
 *
 * - Sale:       reserved on order creation (DecrementStock).
 * - Release:    returned on cancel/refund/abandonment (StockReleaser).
 * - Adjustment: a manual +/- correction by an admin (stocktake, damage…).
 * - Restock:    a manual increase for new incoming stock.
 * - Manual:     an absolute "set to N" by an admin.
 * - Edit:       stock changed via the product/variant editor (Eloquent save).
 */
enum StockMovementType: string
{
    case Sale = 'sale';
    case Release = 'release';
    case Adjustment = 'adjustment';
    case Restock = 'restock';
    case Manual = 'manual';
    case Edit = 'edit';

    /** Translated label for the admin UI. */
    public function label(): string
    {
        return __('admin.stock_movements.type_'.$this->value);
    }
}
