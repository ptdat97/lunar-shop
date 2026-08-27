<?php

namespace Modules\Content\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Content\Filament\Resources\RedirectResource\Pages\CreateRedirect;
use Modules\Content\Filament\Resources\RedirectResource\Pages\EditRedirect;
use Modules\Content\Filament\Resources\RedirectResource\Pages\ListRedirects;
use Modules\Content\Models\Redirect;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-end-on-rectangle';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.redirect.section'))
                    ->schema([
                        TextInput::make('old_url')
                            ->label(__('admin.redirect.from'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('/old-page')
                            ->helperText(__('admin.redirect.from_help')),
                        TextInput::make('new_url')
                            ->label(__('admin.redirect.to'))
                            ->placeholder('/new-page')
                            ->helperText(__('admin.redirect.to_help')),
                        Select::make('status_code')
                            ->label(__('admin.redirect.status_code'))
                            ->options([
                                301 => __('admin.redirect.code_301'),
                                302 => __('admin.redirect.code_302'),
                                410 => __('admin.redirect.code_410'),
                            ])
                            ->default(301),
                        Toggle::make('active')
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
                TextColumn::make('old_url')
                    ->label(__('admin.redirect.from'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('new_url')
                    ->label(__('admin.redirect.to'))
                    ->searchable()
                    ->placeholder(__('admin.redirect.gone')),
                TextColumn::make('status_code')
                    ->label(__('admin.redirect.status_code')),
                IconColumn::make('active')
                    ->label(__('admin.common.active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active'),
                SelectFilter::make('status_code')
                    ->options([
                        301 => '301',
                        302 => '302',
                        410 => '410',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
