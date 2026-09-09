<?php

namespace Modules\Inventory\Panel;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Inventory\Models\StockNotification;

/**
 * Back-in-stock subscriptions: who is waiting for which variant.
 *
 * A read-only queue. The emails go out on their own when stock returns (see
 * BackInStockObserver), so the only thing staff do here is look — and prune a
 * subscription that should not have been taken.
 */
class StockNotificationResource extends PanelResource
{
    public function model(): string
    {
        return StockNotification::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function key(): string
    {
        return 'stock-notifications';
    }

    public function label(): string
    {
        return __('admin.stock_notifications.title');
    }

    public function singular(): string
    {
        return __('admin.stock_notifications.title');
    }

    public function icon(): string
    {
        return 'mail';
    }

    public function permission(): string
    {
        return 'catalog:manage-products';
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::text('email', __('admin.stock_notifications.email'))->onIndex(),
        ];
    }

    /**
     * Eager loads the variant and its product: without them the product column
     * below is a query per row, and this table is the one that grows fastest
     * of any admin screen here.
     */
    public function indexQuery(Builder $query): Builder
    {
        return $query->with('sku.product');
    }

    public function computed(): array
    {
        return [
            'sku' => fn (StockNotification $n) => $n->sku?->sku,
            'product' => fn (StockNotification $n) => $n->sku?->product?->translate('name'),
            'status' => fn (StockNotification $n) => $n->notified_at
                ? __('admin.stock_notifications.status_notified')
                : __('admin.stock_notifications.status_waiting'),
            'subscribed_at' => fn (StockNotification $n) => $n->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function computedLabels(): array
    {
        return [
            'sku' => __('admin.stock_notifications.sku'),
            'product' => __('admin.stock_notifications.product'),
            'status' => __('admin.stock_notifications.status'),
            'subscribed_at' => __('admin.stock_notifications.subscribed_at'),
        ];
    }

    public function searchable(): array
    {
        return ['email'];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }
}
