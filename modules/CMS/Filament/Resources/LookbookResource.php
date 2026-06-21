<?php

namespace Modules\CMS\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\CMS\Models\Lookbook;
use Modules\FileManager\Filament\Forms\MediaPicker;

class LookbookResource extends Resource
{
    protected static ?string $model = Lookbook::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lookbook Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Forms\Set $set) =>
                                $set('slug', \Illuminate\Support\Str::slug($state))
                            ),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Toggle::make('published')
                            ->default(false),
                        Forms\Components\Textarea::make('description'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Cover Image')
                    ->description('Pick from the Media Library.')
                    ->schema([
                        MediaPicker::make('cover_image', type: 'image')
                            ->label('Cover image'),
                    ]),

                Forms\Components\Section::make('Gallery')
                    ->description('Photo collection shown at the top of the lookbook page.')
                    ->schema([
                        Forms\Components\Repeater::make('images')
                            ->relationship()
                            ->schema([
                                MediaPicker::make('image', type: 'image')
                                    ->label('Image')
                                    ->required(),
                                Forms\Components\TextInput::make('caption')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('sort')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(3)
                            ->orderable('sort')
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make('Products')
                    ->description('Shown at the bottom of the lookbook page.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(fn () => \Lunar\Models\Product::all()
                                        ->mapWithKeys(fn ($product) => [$product->id => $product->translateAttribute('name')]))
                                    ->getOptionLabelUsing(fn ($value): ?string => \Lunar\Models\Product::find($value)?->translateAttribute('name'))
                                    ->searchable()
                                    ->required(),
                                Forms\Components\TextInput::make('caption')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('sort')
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
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\IconColumn::make('published')
                    ->boolean(),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Products'),
                Tables\Columns\TextColumn::make('created_at')
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
            'index' => \Modules\CMS\Filament\Resources\LookbookResource\Pages\ListLookbooks::route('/'),
            'create' => \Modules\CMS\Filament\Resources\LookbookResource\Pages\CreateLookbook::route('/create'),
            'edit' => \Modules\CMS\Filament\Resources\LookbookResource\Pages\EditLookbook::route('/{record}/edit'),
        ];
    }
}