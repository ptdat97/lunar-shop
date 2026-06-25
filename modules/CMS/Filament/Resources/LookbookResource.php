<?php

namespace Modules\CMS\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Lunar\Models\Product;
use Modules\CMS\Filament\Resources\LookbookResource\Pages\CreateLookbook;
use Modules\CMS\Filament\Resources\LookbookResource\Pages\EditLookbook;
use Modules\CMS\Filament\Resources\LookbookResource\Pages\ListLookbooks;
use Modules\CMS\Models\Lookbook;
use Modules\FileManager\Filament\Forms\MediaPicker;

class LookbookResource extends Resource
{
    protected static ?string $model = Lookbook::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin.lookbook.section_details'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('admin.common.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('slug', Str::slug($state))
                            ),
                        Forms\Components\TextInput::make('slug')
                            ->label(__('admin.common.slug'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Toggle::make('published')
                            ->label(__('admin.common.published'))
                            ->default(false),
                        Forms\Components\Textarea::make('description')
                            ->label(__('admin.common.description')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('admin.lookbook.section_cover'))
                    ->description(__('admin.lookbook.cover_pick'))
                    ->schema([
                        MediaPicker::make('cover_image', type: 'image')
                            ->label(__('admin.lookbook.cover')),
                    ]),

                Forms\Components\Section::make(__('admin.lookbook.section_gallery'))
                    ->description(__('admin.lookbook.gallery_desc'))
                    ->schema([
                        Forms\Components\Repeater::make('images')
                            ->relationship()
                            ->schema([
                                MediaPicker::make('image', type: 'image')
                                    ->label(__('admin.common.image'))
                                    ->required(),
                                Forms\Components\TextInput::make('caption')
                                    ->label(__('admin.lookbook.caption'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('sort')
                                    ->label(__('admin.common.sort'))
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->orderable('sort')
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make(__('admin.lookbook.section_products'))
                    ->description(__('admin.lookbook.products_desc'))
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label(__('admin.lookbook.product'))
                                    ->options(fn () => Product::all()
                                        ->mapWithKeys(fn ($product) => [$product->id => $product->translateAttribute('name')]))
                                    ->getOptionLabelUsing(fn ($value): ?string => Product::find($value)?->translateAttribute('name'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('caption')
                                    ->label(__('admin.lookbook.caption'))
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('sort')
                                    ->label(__('admin.common.sort'))
                                    ->numeric()
                                    ->default(0),
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
                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.common.title'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.common.slug'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('published')
                    ->label(__('admin.common.published'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label(__('admin.lookbook.product')),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('published'),
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
            'index' => ListLookbooks::route('/'),
            'create' => CreateLookbook::route('/create'),
            'edit' => EditLookbook::route('/{record}/edit'),
        ];
    }
}
