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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Content\Filament\Resources\BannerResource\Pages\CreateBanner;
use Modules\Content\Filament\Resources\BannerResource\Pages\EditBanner;
use Modules\Content\Filament\Resources\BannerResource\Pages\ListBanners;
use Modules\Content\Models\Banner;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.banner.section_details'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('admin.common.title'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('subtitle')
                            ->label(__('admin.banner.subtitle'))
                            ->maxLength(255),
                        TextInput::make('button_text')
                            ->label(__('admin.banner.button_text'))
                            ->maxLength(255),
                        TextInput::make('button_url')
                            ->label(__('admin.banner.button_url'))
                            ->maxLength(255),
                        Select::make('position')
                            ->label(__('admin.banner.position'))
                            ->options([
                                'center' => __('admin.banner.pos_center'),
                                'left' => __('admin.banner.pos_left'),
                                'right' => __('admin.banner.pos_right'),
                            ])
                            ->default('center'),
                        Toggle::make('active')
                            ->label(__('admin.common.active'))
                            ->default(true),
                        TextInput::make('sort')
                            ->label(__('admin.common.sort'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),

                Section::make(__('admin.banner.section_images'))
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
                TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subtitle')
                    ->label(__('admin.banner.subtitle'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('active')
                    ->label(__('admin.common.active'))
                    ->boolean(),
                TextColumn::make('position')
                    ->label(__('admin.banner.position')),
                TextColumn::make('sort')
                    ->label(__('admin.common.sort'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
