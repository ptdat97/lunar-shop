<?php

namespace Modules\Content\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Lunar\Core\Models\Product;
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Content\Filament\Resources\LookbookResource\Pages\CreateLookbook;
use Modules\Content\Filament\Resources\LookbookResource\Pages\EditLookbook;
use Modules\Content\Filament\Resources\LookbookResource\Pages\ListLookbooks;
use Modules\Content\Models\Lookbook;

class LookbookResource extends Resource
{
    protected static ?string $model = Lookbook::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.lookbook.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.lookbook.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.lookbook.section_details'))
                    ->schema([
                        TextInput::make('title')
                            ->label(__('admin.common.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set) => $set('slug', Str::slug($state))
                            ),
                        TextInput::make('slug')
                            ->label(__('admin.common.slug'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Toggle::make('published')
                            ->label(__('admin.common.published'))
                            ->default(false),
                        Textarea::make('description')
                            ->label(__('admin.common.description')),
                    ])
                    ->columns(2),

                Section::make(__('admin.lookbook.section_cover'))
                    ->description(__('admin.lookbook.cover_pick'))
                    ->schema([
                        MediaPicker::make('cover_image', type: 'image')
                            ->label(__('admin.lookbook.cover')),
                    ]),

                Section::make(__('admin.lookbook.section_gallery'))
                    ->description(__('admin.lookbook.gallery_desc'))
                    ->schema([
                        Repeater::make('images')
                            ->relationship()
                            ->schema([
                                MediaPicker::make('image', type: 'image')
                                    ->label(__('admin.common.image'))
                                    ->required(),
                                TextInput::make('caption')
                                    ->label(__('admin.lookbook.caption'))
                                    ->maxLength(255),
                                TextInput::make('sort')
                                    ->label(__('admin.common.sort'))
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->orderable('sort')
                            ->defaultItems(0),
                    ]),

                Section::make(__('admin.lookbook.section_products'))
                    ->description(__('admin.lookbook.products_desc'))
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('admin.lookbook.product'))
                                    ->options(fn () => Product::all()
                                        ->mapWithKeys(fn ($product) => [$product->id => $product->translate('name')]))
                                    ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->translateAttribute('name'))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('caption')
                                    ->label(__('admin.lookbook.caption'))
                                    ->maxLength(255),
                                TextInput::make('sort')
                                    ->label(__('admin.common.sort'))
                                    ->numeric()
                                    ->default(0),
                                // Optional hotspot placement: pin the product on a
                                // photo at (x%, y%). Leave blank for "shop the set"
                                // only (no pin rendered).
                                Select::make('image_id')
                                    ->label(__('admin.lookbook.pin_image'))
                                    ->relationship('image', 'caption')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->caption ?: ('#'.$record->id))
                                    ->helperText(__('admin.lookbook.pin_image_help'))
                                    ->placeholder(__('admin.lookbook.pin_cover'))
                                    ->nullable(),
                                TextInput::make('pos_x')
                                    ->label(__('admin.lookbook.pos_x'))
                                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                                    ->nullable(),
                                TextInput::make('pos_y')
                                    ->label(__('admin.lookbook.pos_y'))
                                    ->numeric()->minValue(0)->maxValue(100)->suffix('%')
                                    ->nullable(),
                            ])
                            ->columns(3)
                            ->orderable('sort'),
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
                TextColumn::make('slug')
                    ->label(__('admin.common.slug'))
                    ->searchable(),
                IconColumn::make('published')
                    ->label(__('admin.common.published'))
                    ->boolean(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('admin.lookbook.product')),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('published'),
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
            'index' => ListLookbooks::route('/'),
            'create' => CreateLookbook::route('/create'),
            'edit' => EditLookbook::route('/{record}/edit'),
        ];
    }
}
