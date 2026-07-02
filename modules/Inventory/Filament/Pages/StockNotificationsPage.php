<?php

namespace Modules\Inventory\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Models\StockNotification;

/**
 * Back-in-stock mailing list: every "notify me when it's back" subscription a
 * shopper left against an out-of-stock variant. Read-only apart from removing
 * stale rows — the emails are sent automatically when a variant is restocked
 * (ProductVariantObserver → BackInStockNotifier), then the row is marked
 * notified. This page lets the merchandiser see demand (who's waiting for what)
 * and prune subscriptions.
 */
class StockNotificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $slug = 'stock-notifications';

    protected static string $view = 'inventory::filament.pages.stock-notifications';

    /** Sits just after Stock Levels in the Sales group. */
    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.stock_notifications.title');
    }

    public function getTitle(): string
    {
        return __('admin.stock_notifications.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    /** Badge: how many subscribers are still waiting to be emailed. */
    public static function getNavigationBadge(): ?string
    {
        $pending = StockNotification::query()->pending()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->defaultSort('stock_notifications.created_at', 'desc')
            ->columns([
                TextColumn::make('email')
                    ->label(__('admin.stock_notifications.email'))
                    ->searchable()
                    ->copyable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('product')
                    ->label(__('admin.stock_notifications.product'))
                    ->getStateUsing(fn (StockNotification $r) => $r->variant?->product?->translateAttribute('name'))
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas(
                        'variant.product',
                        fn (Builder $p) => $p->where('attribute_data->name->en->value', 'like', "%{$search}%")
                    ))
                    ->wrap(),

                TextColumn::make('variant.sku')
                    ->label(__('admin.stock_notifications.sku'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin.stock_notifications.status'))
                    ->badge()
                    ->state(fn (StockNotification $r) => $r->notified_at
                        ? __('admin.stock_notifications.status_notified')
                        : __('admin.stock_notifications.status_waiting'))
                    ->color(fn (StockNotification $r): string => $r->notified_at ? 'success' : 'warning'),

                TextColumn::make('created_at')
                    ->label(__('admin.stock_notifications.subscribed_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('notified_at')
                    ->label(__('admin.stock_notifications.notified_at'))
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.stock_notifications.status'))
                    ->options([
                        'waiting' => __('admin.stock_notifications.status_waiting'),
                        'notified' => __('admin.stock_notifications.status_notified'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => match ($data['value'] ?? null) {
                        'waiting' => $q->whereNull('notified_at'),
                        'notified' => $q->whereNotNull('notified_at'),
                        default => $q,
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label(__('admin.stock_notifications.unsubscribe')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading(__('admin.stock_notifications.empty_heading'))
            ->emptyStateDescription(__('admin.stock_notifications.empty_desc'));
    }

    protected function baseQuery(): Builder
    {
        // Eager-load the variant + its product so the product-name column and SKU
        // render without an N+1 per row.
        return StockNotification::query()->with('variant.product');
    }
}
