<?php

namespace Modules\Menu\Filament\Resources;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Models\Collection as LunarCollection;
use Modules\Menu\Filament\Resources\MenuResource\Pages;
use Modules\Menu\Models\Menu;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    protected static ?string $navigationLabel = 'Menus';

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    protected static ?string $modelLabel = 'Menu';

    public static function form(Form $form): Form
    {
        return $form->schema([
            FormSection::make()->columns(2)->schema([
                TextInput::make('name')->required(),
                TextInput::make('handle')->required()->helperText('e.g. header, footer'),
            ]),

            // The whole tree lives under `tree` (built on fill, flattened on save).
            Repeater::make('tree')
                ->label('Menu items')
                ->schema(static::itemSchema())
                ->collapsible()
                ->reorderable()
                ->itemLabel(fn (array $state) => ($state['label'] ?? 'Item').' ['.($state['type'] ?? 'link').']')
                ->columnSpanFull()
                ->defaultItems(0),
        ]);
    }

    /**
     * Schema for one top-level item. Mega items get nested columns + banners.
     *
     * @return array<int, Component>
     */
    protected static function itemSchema(): array
    {
        return [
            FormSection::make()->columns(3)->schema([
                Select::make('type')
                    ->options(['link' => 'Link', 'dropdown' => 'Dropdown', 'mega' => 'Mega menu', 'footer-column' => 'Footer column'])
                    ->default('link')->required()->live(),
                TextInput::make('label')->required(),
                TextInput::make('badge')->placeholder('New / Hot'),
            ]),

            // Destination (shared by link/dropdown/mega top-level)
            FormSection::make()->columns(2)->schema([
                TextInput::make('url')->label('URL')->placeholder('/search or https://…'),
                Select::make('collection_id')->label('Or link a collection')
                    ->options(fn () => static::collectionOptions())->searchable(),
            ]),

            // Dropdown → flat list of links
            Repeater::make('children')
                ->label('Links')
                ->visible(fn (Get $get) => in_array($get('type'), ['dropdown', 'footer-column']))
                ->schema(static::linkSchema())
                ->collapsible()->reorderable()
                ->itemLabel(fn (array $state) => $state['label'] ?? 'Link'),

            // Mega → columns (each with its own links) + optional banners
            Repeater::make('children')
                ->label('Columns & banners')
                ->visible(fn (Get $get) => $get('type') === 'mega')
                ->schema([
                    Select::make('type')
                        ->options(['mega-column' => 'Column', 'banner' => 'Banner'])
                        ->default('mega-column')->required()->live(),
                    TextInput::make('label')->label('Heading / alt'),
                    FileUpload::make('image')->label('Banner image')
                        ->image()->disk('media')->directory('menus/banners')
                        ->visible(fn (Get $get) => $get('type') === 'banner'),
                    TextInput::make('url')->label('Banner link')
                        ->visible(fn (Get $get) => $get('type') === 'banner'),
                    Repeater::make('children')
                        ->label('Links')
                        ->visible(fn (Get $get) => $get('type') === 'mega-column')
                        ->schema(static::linkSchema())
                        ->collapsible()->reorderable()
                        ->itemLabel(fn (array $state) => $state['label'] ?? 'Link'),
                ])
                ->collapsible()->reorderable()
                ->itemLabel(fn (array $state) => ($state['label'] ?? 'Column').' ['.($state['type'] ?? 'mega-column').']'),
        ];
    }

    /**
     * Schema for a leaf link (label + url or collection).
     *
     * @return array<int, Component>
     */
    protected static function linkSchema(): array
    {
        return [
            TextInput::make('label')->required(),
            TextInput::make('url')->label('URL'),
            Select::make('collection_id')->label('Collection')
                ->options(fn () => static::collectionOptions())->searchable(),
            TextInput::make('badge')->placeholder('New'),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function collectionOptions(): array
    {
        return LunarCollection::query()->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->translateAttribute('name')])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('handle')->badge(),
            TextColumn::make('items_count')->counts('items')->label('Items'),
        ])->actions([
            EditAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMenus::route('/'),
        ];
    }
}
