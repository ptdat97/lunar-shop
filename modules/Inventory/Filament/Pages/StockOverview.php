<?php

namespace Modules\Inventory\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\ProductVariant;

/**
 * Admin stock overview: every variant's stock at a glance, lowest first, with a
 * Status badge (out / low / in stock) and quick filters to focus on what needs
 * reordering. Read-only — editing happens on the variant itself (Lunar's
 * ProductVariant resource). Stock is decremented automatically on order
 * placement by DecrementStock, and "notify me" subscribers (the Waiting column)
 * are emailed when a variant is restocked.
 *
 * Note: variants set to `purchasable = always` never run out (unlimited), so
 * their status shows "Unlimited" rather than a stock warning.
 */
class StockOverview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    public static function getNavigationLabel(): string
    {
        return __('admin.inventory.title');
    }

    public function getTitle(): string
    {
        return __('admin.inventory.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    protected static ?string $slug = 'stock-levels';

    protected static string $view = 'inventory::filament.pages.stock-overview';

    /** Admin-configurable "low stock" threshold (Settings → default). */
    protected function lowThreshold(): int
    {
        return app(\Modules\Inventory\Services\InventoryService::class)->lowStockThreshold();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->defaultSort('stock', 'asc')
            ->columns([
                TextColumn::make('product.attribute_data.name')
                    ->label(__('admin.inventory.product'))
                    ->getStateUsing(fn (ProductVariant $r) => $r->product?->translateAttribute('name'))
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas(
                        'product',
                        fn (Builder $p) => $p->where('attribute_data->name->en->value', 'like', "%{$search}%")
                    ))
                    ->wrap(),
                TextColumn::make('sku')
                    ->label(__('admin.inventory.sku'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.inventory.status'))
                    ->badge()
                    ->state(fn (ProductVariant $r) => $this->status($r))
                    ->color(fn (string $state): string => match ($state) {
                        'Out of stock' => 'danger',
                        'Low stock' => 'warning',
                        'Unlimited' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('stock')
                    ->label(__('admin.inventory.stock'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('backorder')
                    ->label(__('admin.inventory.backorder'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('purchasable')
                    ->label(__('admin.inventory.mode'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('waiting')
                    ->label(__('admin.inventory.waiting'))
                    ->badge()
                    ->color('info')
                    ->tooltip(__('admin.inventory.waiting_tip')),
            ])
            ->filters([
                SelectFilter::make('availability')
                    ->label(__('admin.inventory.stock_status'))
                    ->options([
                        'out' => __('admin.inventory.filter_out'),
                        'low' => __('admin.inventory.filter_low'),
                        'tracked' => __('admin.inventory.filter_tracked'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => match ($data['value'] ?? null) {
                        'out' => $q->where('purchasable', '!=', 'always')->where('stock', '<=', 0),
                        'low' => $q->where('purchasable', '!=', 'always')->whereBetween('stock', [1, $this->lowThreshold()]),
                        'tracked' => $q->where('purchasable', '!=', 'always'),
                        default => $q,
                    }),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Nothing to restock')
            ->emptyStateDescription('No product variants match this filter. Stock is decremented automatically when orders are placed.');
    }

    /** Human-readable stock status for a variant. */
    protected function status(ProductVariant $variant): string
    {
        if ($variant->purchasable === 'always') {
            return 'Unlimited';
        }

        return match (true) {
            $variant->stock <= 0 => 'Out of stock',
            $variant->stock <= $this->lowThreshold() => 'Low stock',
            default => 'In stock',
        };
    }

    protected function baseQuery(): Builder
    {
        $table = (new ProductVariant)->getTable();

        // Show every variant — lowest stock first — so the page is a real stock
        // table, not just an (often empty) alert list. Filters narrow to
        // out/low/tracked when the merchandiser wants to focus.
        return ProductVariant::query()
            ->with('product')
            // Count of pending "notify me" subscribers, without needing a
            // relation on Lunar's variant model.
            ->selectRaw("{$table}.*, ("
                .'select count(*) from stock_notifications '
                ."where stock_notifications.product_variant_id = {$table}.id "
                .'and stock_notifications.notified_at is null) as waiting');
    }
}
