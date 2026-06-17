<?php

namespace Modules\CMS\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\CMS\Models\Redirect;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-end-on-rectangle';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Redirect Details')
                    ->schema([
                        Forms\Components\TextInput::make('old_url')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('/old-page')
                            ->helperText('The URL to redirect from (e.g. /old-page)'),
                        Forms\Components\TextInput::make('new_url')
                            ->placeholder('/new-page')
                            ->helperText('The URL to redirect to. Leave empty to return 410 Gone.'),
                        Forms\Components\Select::make('status_code')
                            ->options([
                                301 => '301 - Permanent',
                                302 => '302 - Temporary',
                                410 => '410 - Gone',
                            ])
                            ->default(301),
                        Forms\Components\Toggle::make('active')
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
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('new_url')
                    ->searchable()
                    ->placeholder('(gone)'),
                Tables\Columns\TextColumn::make('status_code'),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
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
            'index' => \Modules\CMS\Filament\Resources\RedirectResource\Pages\ListRedirects::route('/'),
            'create' => \Modules\CMS\Filament\Resources\RedirectResource\Pages\CreateRedirect::route('/create'),
            'edit' => \Modules\CMS\Filament\Resources\RedirectResource\Pages\EditRedirect::route('/{record}/edit'),
        ];
    }
}