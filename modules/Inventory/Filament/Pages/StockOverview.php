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

    protected static ?string $title = 'Stock Levels';

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    protected static ?string $slug = 'stock-levels';

    protected static string $view = 'inventory::filament.pages.stock-overview';

    /** Stock at or below this is considered "low". */
    public const LOW_THRESHOLD = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->defaultSort('stock', 'asc')
            ->columns([
                TextColumn::make('product.attribute_data.name')
                    ->label('Product')
                    ->getStateUsing(fn (ProductVariant $r) => $r->product?->translateAttribute('name'))
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas(
                        'product',
                        fn (Builder $p) => $p->where('attribute_data->name->en->value', 'like', "%{$search}%")
                    ))
                    ->wrap(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ProductVariant $r) => $this->status($r))
                    ->color(fn (string $state): string => match ($state) {
                        'Out of stock' => 'danger',
                        'Low stock' => 'warning',
                        'Unlimited' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('backorder')
                    ->label('Backorder')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('purchasable')
                    ->label('Mode')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('waiting')
                    ->label('Waiting')
                    ->badge()
                    ->color('info')
                    ->tooltip('Shoppers waiting for a back-in-stock email'),
            ])
            ->filters([
                SelectFilter::make('availability')
                    ->label('Stock status')
                    ->options([
                        'out' => 'Out of stock',
                        'low' => 'Low stock',
                        'tracked' => 'Tracked (not unlimited)',
                    ])
                    ->query(fn (Builder $q, array $data): Builder => match ($data['value'] ?? null) {
                        'out' => $q->where('purchasable', '!=', 'always')->where('stock', '<=', 0),
                        'low' => $q->where('purchasable', '!=', 'always')->whereBetween('stock', [1, self::LOW_THRESHOLD]),
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
            $variant->stock <= self::LOW_THRESHOLD => 'Low stock',
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
