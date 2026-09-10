<?php

namespace Modules\Inventory\Panel;

use Lunar\Core\Models\Order;
use Lunar\Panel\Dashboard\DashboardRange;
use Lunar\Panel\Dashboard\Widget;
use Lunar\Panel\Dashboard\WidgetSpan;
use Lunar\Panel\Support\Position;
use Modules\Inventory\Services\InventoryService;

/**
 * Orders that have been holding stock for too long.
 *
 * Committed units only return to the sellable pool when an order is dispatched
 * or cancelled. An order that is paid and then forgotten holds its units
 * forever, and the shelf quietly stops selling with nothing to show why — the
 * worst kind of inventory problem, because everything looks fine.
 *
 * The old Stock Overview page carried this as a banner. It went with the rest
 * of Filament, and `InventoryService::staleCommitments()` has been answering to
 * nobody since. This puts it back where it belongs: next to Lunar's own
 * low-stock card, on the screen staff already open.
 *
 * `$range` is ignored: an order stuck since March is stuck whether or not the
 * dashboard is showing the last seven days.
 */
class StaleCommitmentsWidget extends Widget
{
    public function __construct(protected InventoryService $inventory) {}

    public function key(): string
    {
        return 'shop-stale-commitments';
    }

    public function component(): string
    {
        return 'shop::StaleCommitmentsWidget';
    }

    public function label(): string
    {
        return __('admin.stale_commitments.title');
    }

    public function description(): ?string
    {
        return __('admin.stale_commitments.description', ['days' => InventoryService::STALE_COMMITMENT_DAYS]);
    }

    public function icon(): ?string
    {
        return 'alertTriangle';
    }

    public function span(): WidgetSpan
    {
        return WidgetSpan::Half;
    }

    public function permission(): ?string
    {
        return 'sales:manage-orders';
    }

    public function position(): Position
    {
        return Position::priority(70);
    }

    public function data(DashboardRange $range): array
    {
        $orders = $this->inventory->staleCommitments();

        return [
            'count' => $orders->count(),
            'emptyLabel' => __('admin.stale_commitments.none'),
            'heldLabel' => __('admin.stale_commitments.held_since'),
            // Capped: this is a warning that something needs attention, not a
            // report. A shop with 400 stuck orders needs the number and the
            // oldest few, not 400 rows on its dashboard.
            'orders' => $orders->take(8)->map(fn (Order $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'placed_at' => $order->placed_at?->format('d/m/Y'),
                'days' => $order->placed_at?->diffInDays(now()),
                'url' => route('panel.orders.show', $order->id),
            ])->values()->all(),
        ];
    }
}
