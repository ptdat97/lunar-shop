<?php

namespace Modules\Inventory\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Models\Currency;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStockAdjustmentException;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Services\StockLedger;

/**
 * Admin stock overview: every SKU's stock at a glance, lowest first, with a
 * Status badge (out / low / in stock / disabled) and quick filters to focus on
 * what needs reordering. Read-only — editing happens on the product's variant
 * builder. Stock is decremented automatically on order placement by
 * DecrementStock, and "notify me" subscribers (the Waiting column) are emailed
 * when a SKU is restocked.
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

    /**
     * Stock value + low/out/tracked counts, rendered inline in the page blade
     * (NOT a header widget): a page header widget is a deferred Livewire child,
     * which is exactly what triggers a 419/page-expired on this database-session
     * setup. Computed server-side here, displayed as plain markup.
     *
     * @return array<int, array{label: string, value: string, description: string, icon: string, color: string}>
     */
    public function stats(): array
    {
        $inventory = app(InventoryService::class);
        $factor = (float) (Currency::getDefault()?->factor ?: 100);

        return [
            [
                'label' => __('admin.inventory.stat_value'),
                'value' => number_format($inventory->inventoryValueMinor() / $factor, 0),
                'description' => __('admin.inventory.stat_value_desc'),
                'icon' => 'heroicon-m-banknotes',
                'color' => 'primary',
            ],
            [
                'label' => __('admin.inventory.stat_low'),
                'value' => (string) $inventory->lowCount(),
                'description' => __('admin.inventory.stat_low_desc'),
                'icon' => 'heroicon-m-exclamation-triangle',
                'color' => 'warning',
            ],
            [
                'label' => __('admin.inventory.stat_out'),
                'value' => (string) $inventory->outCount(),
                'description' => __('admin.inventory.stat_out_desc'),
                'icon' => 'heroicon-m-x-circle',
                'color' => 'danger',
            ],
            [
                'label' => __('admin.inventory.stat_tracked'),
                'value' => (string) $inventory->trackedCount(),
                'description' => __('admin.inventory.stat_tracked_desc'),
                'icon' => 'heroicon-m-cube',
                'color' => 'gray',
            ],
        ];
    }

    /** Admin-configurable "low stock" threshold (Settings → default). */
    protected function lowThreshold(): int
    {
        return app(InventoryService::class)->lowStockThreshold();
    }

    /** Reason options shared by the adjust + bulk forms. */
    protected function reasonOptions(): array
    {
        return [
            'restock' => __('admin.inventory.reason_restock'),
            'stocktake' => __('admin.inventory.reason_stocktake'),
            'damage' => __('admin.inventory.reason_damage'),
            'other' => __('admin.inventory.reason_other'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->defaultSort('quantity', 'asc')
            ->columns([
                TextColumn::make('product.attribute_data.name')
                    ->label(__('admin.inventory.product'))
                    ->getStateUsing(fn (ProductSku $r) => $r->product?->translateAttribute('name'))
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas(
                        'product',
                        fn (Builder $p) => $p->where('attribute_data->name->en->value', 'like', "%{$search}%")
                    ))
                    ->description(fn (ProductSku $r) => $r->getOption())
                    ->wrap(),
                TextColumn::make('sku')
                    ->label(__('admin.inventory.sku'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.inventory.status'))
                    ->badge()
                    ->state(fn (ProductSku $r) => $this->status($r))
                    ->color(fn (string $state): string => match ($state) {
                        'Out of stock' => 'danger',
                        'Low stock' => 'warning',
                        'Disabled' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('quantity')
                    ->label(__('admin.inventory.stock'))
                    ->numeric()
                    ->sortable(),
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
                        'out' => $q->where('quantity', '<=', 0),
                        'low' => $q->whereBetween('quantity', [1, $this->lowThreshold()]),
                        'tracked' => $q,
                        default => $q,
                    }),
            ])
            ->actions([
                $this->adjustAction(),
                $this->historyAction(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    $this->bulkAdjustAction(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('Nothing to restock')
            ->emptyStateDescription('No SKUs match this filter. Stock is decremented automatically when orders are placed.');
    }

    /** Row action: +/- or set a single variant's stock, with a reason. */
    protected function adjustAction(): Action
    {
        return Action::make('adjust')
            ->label(__('admin.inventory.adjust'))
            ->icon('heroicon-m-pencil-square')
            ->modalWidth('md')
            ->form([
                Select::make('mode')
                    ->label(__('admin.inventory.adjust_mode'))
                    ->options([
                        'increment' => __('admin.inventory.mode_increment'),
                        'decrement' => __('admin.inventory.mode_decrement'),
                        'set' => __('admin.inventory.mode_set'),
                    ])
                    ->default('increment')
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('admin.inventory.quantity'))
                    ->numeric()->minValue(0)->required(),
                Select::make('reason')
                    ->label(__('admin.inventory.reason'))
                    ->options($this->reasonOptions())
                    ->default('restock')
                    ->required(),
                TextInput::make('note')
                    ->label(__('admin.inventory.note'))
                    ->maxLength(255),
            ])
            ->action(fn (ProductSku $record, array $data) => $this->applyAdjustment($record->id, $data));
    }

    /** Bulk action: set/increment stock across the selected variants. */
    protected function bulkAdjustAction(): BulkAction
    {
        return BulkAction::make('bulkAdjust')
            ->label(__('admin.inventory.bulk_adjust'))
            ->icon('heroicon-m-pencil-square')
            ->modalWidth('md')
            ->form([
                Select::make('mode')
                    ->label(__('admin.inventory.adjust_mode'))
                    ->options([
                        'increment' => __('admin.inventory.mode_increment'),
                        'set' => __('admin.inventory.mode_set'),
                    ])
                    ->default('increment')
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('admin.inventory.quantity'))
                    ->numeric()->minValue(0)->required(),
                Select::make('reason')
                    ->label(__('admin.inventory.reason'))
                    ->options($this->reasonOptions())
                    ->default('restock')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $ok = 0;
                $failed = 0;

                foreach ($records as $record) {
                    try {
                        $this->applyAdjustment($record->id, $data, notify: false);
                        $ok++;
                    } catch (InvalidStockAdjustmentException) {
                        $failed++;
                    }
                }

                Notification::make()
                    ->title(__('admin.inventory.bulk_done', ['ok' => $ok, 'failed' => $failed]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Apply one adjustment through the ledger service (§4 — no stock logic in
     * the page). increment/decrement → adjust(); set → set(). restock reasons
     * are typed Restock so they show distinctly in the history.
     */
    protected function applyAdjustment(int $skuId, array $data, bool $notify = true): void
    {
        $ledger = app(StockLedger::class);
        $staff = Filament::auth()->user();
        $quantity = (int) $data['quantity'];
        $reason = $data['reason'];
        $meta = ! empty($data['note']) ? ['note' => $data['note']] : [];

        try {
            match ($data['mode']) {
                'set' => $ledger->set($skuId, $quantity, StockMovementType::Manual, $reason, $staff, $meta),
                'decrement' => $ledger->adjust($skuId, -$quantity, StockMovementType::Adjustment, $reason, $staff, $meta),
                default => $ledger->adjust(
                    $skuId,
                    $quantity,
                    $reason === 'restock' ? StockMovementType::Restock : StockMovementType::Adjustment,
                    $reason,
                    $staff,
                    $meta,
                ),
            };
        } catch (InvalidStockAdjustmentException $e) {
            if (! $notify) {
                throw $e;
            }

            Notification::make()->title(__('admin.inventory.adjust_negative'))->danger()->send();

            return;
        }

        if ($notify) {
            Notification::make()->title(__('admin.inventory.adjust_done'))->success()->send();
        }
    }

    /** Row action: show this variant's recent stock movements in a modal. */
    protected function historyAction(): Action
    {
        return Action::make('history')
            ->label(__('admin.inventory.history'))
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->modalHeading(__('admin.inventory.history'))
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalContent(fn (ProductSku $record) => view(
                'inventory::filament.partials.stock-history',
                [
                    'movements' => StockMovement::where('product_sku_id', $record->id)
                        ->with('causer')
                        ->latest('created_at')
                        ->latest('id')
                        ->limit(50)
                        ->get(),
                ],
            ));
    }

    /** Human-readable stock status for a SKU. */
    protected function status(ProductSku $sku): string
    {
        if ($sku->status === 'disabled') {
            return 'Disabled';
        }

        return match (true) {
            $sku->quantity <= 0 => 'Out of stock',
            $sku->quantity <= $this->lowThreshold() => 'Low stock',
            default => 'In stock',
        };
    }

    protected function baseQuery(): Builder
    {
        $table = (new ProductSku)->getTable();

        // Show every SKU — lowest stock first — so the page is a real stock
        // table, not just an (often empty) alert list. Filters narrow to
        // out/low/tracked when the merchandiser wants to focus.
        return ProductSku::query()
            ->with('product')
            // Count of pending "notify me" subscribers per SKU.
            ->selectRaw("{$table}.*, ("
                .'select count(*) from stock_notifications '
                ."where stock_notifications.product_sku_id = {$table}.id "
                .'and stock_notifications.notified_at is null) as waiting');
    }
}
