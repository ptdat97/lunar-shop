<?php

namespace Modules\CMS\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\CMS\Filament\Resources\BannerResource\Pages\CreateBanner;
use Modules\CMS\Filament\Resources\BannerResource\Pages\EditBanner;
use Modules\CMS\Filament\Resources\BannerResource\Pages\ListBanners;
use Modules\CMS\Models\Banner;
use Modules\FileManager\Filament\Forms\MediaPicker;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Banner Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subtitle')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('button_text')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('button_url')
                            ->maxLength(255),
                        Forms\Components\Select::make('position')
                            ->options([
                                'center' => 'Center',
                                'left' => 'Left',
                                'right' => 'Right',
                            ])
                            ->default('center'),
                        Forms\Components\Toggle::make('active')
                            ->default(true),
                        Forms\Components\TextInput::make('sort')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Images')
                    ->description('Pick from the Media Library. Upload new files there first if needed.')
                    ->schema([
                        MediaPicker::make('image', type: 'image')
                            ->label('Image'),
                        MediaPicker::make('mobile_image', type: 'image')
                            ->label('Mobile image'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subtitle')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('position'),
                Tables\Columns\TextColumn::make('sort')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort')
            ->defaultSort('sort');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }
}
