<?php

namespace Modules\Content\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Content\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use Modules\Content\Filament\Resources\RedirectResource\Pages\EditRedirect;
use Modules\Content\Filament\Resources\RedirectResource\Pages\ListRedirects;
use Modules\Content\Models\Redirect;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-end-on-rectangle';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.redirect.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.redirect.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin.redirect.section'))
                    ->schema([
                        Forms\Components\TextInput::make('old_url')
                            ->label(__('admin.redirect.from'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('/old-page')
                            ->helperText(__('admin.redirect.from_help')),
                        Forms\Components\TextInput::make('new_url')
                            ->label(__('admin.redirect.to'))
                            ->placeholder('/new-page')
                            ->helperText(__('admin.redirect.to_help')),
                        Forms\Components\Select::make('status_code')
                            ->label(__('admin.redirect.status_code'))
                            ->options([
                                301 => __('admin.redirect.code_301'),
                                302 => __('admin.redirect.code_302'),
                                410 => __('admin.redirect.code_410'),
                            ])
                            ->default(301),
                        Forms\Components\Toggle::make('active')
                            ->label(__('admin.common.active'))
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('old_url')
                    ->label(__('admin.redirect.from'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('new_url')
                    ->label(__('admin.redirect.to'))
                    ->searchable()
                    ->placeholder(__('admin.redirect.gone')),
                Tables\Columns\TextColumn::make('status_code')
                    ->label(__('admin.redirect.status_code')),
                Tables\Columns\IconColumn::make('active')
                    ->label(__('admin.common.active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
                Tables\Filters\SelectFilter::make('status_code')
                    ->options([
                        301 => '301',
                        302 => '302',
                        410 => '410',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
