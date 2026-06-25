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

    public static function getModelLabel(): string
    {
        return __('admin.banner.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.banner.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin.banner.section_details'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('admin.common.title'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subtitle')
                            ->label(__('admin.banner.subtitle'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('button_text')
                            ->label(__('admin.banner.button_text'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('button_url')
                            ->label(__('admin.banner.button_url'))
                            ->maxLength(255),
                        Forms\Components\Select::make('position')
                            ->label(__('admin.banner.position'))
                            ->options([
                                'center' => __('admin.banner.pos_center'),
                                'left' => __('admin.banner.pos_left'),
                                'right' => __('admin.banner.pos_right'),
                            ])
                            ->default('center'),
                        Forms\Components\Toggle::make('active')
                            ->label(__('admin.common.active'))
                            ->default(true),
                        Forms\Components\TextInput::make('sort')
                            ->label(__('admin.common.sort'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('admin.banner.section_images'))
                    ->description(__('admin.banner.images_pick'))
                    ->schema([
                        MediaPicker::make('image', type: 'image')
                            ->label(__('admin.banner.image')),
                        MediaPicker::make('mobile_image', type: 'image')
                            ->label(__('admin.banner.mobile_image')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subtitle')
                    ->label(__('admin.banner.subtitle'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('active')
                    ->label(__('admin.common.active'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('admin.banner.position')),
                Tables\Columns\TextColumn::make('sort')
                    ->label(__('admin.common.sort'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
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
