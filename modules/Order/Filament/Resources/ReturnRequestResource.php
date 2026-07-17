<?php

namespace Modules\Order\Filament\Resources;

use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Order\Filament\Resources\ReturnRequestResource\Pages\ListReturnRequests;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReturnService;

/**
 * Admin view of customer return (RMA) requests. Staff approve (optionally
 * refunding via the payment gateway) or reject; created by customers on the
 * storefront, so no create form here.
 */
class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    public static function getNavigationLabel(): string
    {
        return __('admin.returns.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin.returns.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.returns.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['order', 'lines']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('reference')->label(__('admin.returns.reference'))->searchable(),
                Tables\Columns\TextColumn::make('order.reference')->label(__('admin.returns.order'))->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.returns.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        ReturnRequest::REQUESTED => 'warning',
                        ReturnRequest::APPROVED => 'info',
                        ReturnRequest::REFUNDED, ReturnRequest::COMPLETED => 'success',
                        ReturnRequest::REJECTED => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')->label(__('admin.returns.reason'))->limit(30),
                Tables\Columns\TextColumn::make('lines_count')->counts('lines')->label(__('admin.returns.items')),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.returns.requested_at'))->dateTime('d/m/Y H:i'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    ReturnRequest::REQUESTED => __('admin.returns.status_requested'),
                    ReturnRequest::APPROVED => __('admin.returns.status_approved'),
                    ReturnRequest::REJECTED => __('admin.returns.status_rejected'),
                    ReturnRequest::REFUNDED => __('admin.returns.status_refunded'),
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label(__('admin.returns.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ReturnRequest $record) => $record->status === ReturnRequest::REQUESTED)
                    ->form([
                        Forms\Components\Toggle::make('refund')->label(__('admin.returns.also_refund'))->default(true),
                        Forms\Components\Textarea::make('staff_note')->label(__('admin.returns.staff_note')),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        try {
                            app(ReturnService::class)->approve($record, (bool) ($data['refund'] ?? false), $data['staff_note'] ?? null);
                            Notification::make()->title(__('admin.returns.approved'))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('admin.returns.refund_failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('refund')
                    ->label(__('admin.returns.refund'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (ReturnRequest $record) => $record->status === ReturnRequest::APPROVED)
                    ->requiresConfirmation()
                    ->action(function (ReturnRequest $record) {
                        try {
                            app(ReturnService::class)->refund($record);
                            Notification::make()->title(__('admin.returns.refunded'))->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title(__('admin.returns.refund_failed'))->body($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label(__('admin.returns.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (ReturnRequest $record) => $record->status === ReturnRequest::REQUESTED)
                    ->form([
                        Forms\Components\Textarea::make('staff_note')->label(__('admin.returns.staff_note')),
                    ])
                    ->action(function (ReturnRequest $record, array $data) {
                        app(ReturnService::class)->reject($record, $data['staff_note'] ?? null);
                        Notification::make()->title(__('admin.returns.rejected'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
        ];
    }
}
