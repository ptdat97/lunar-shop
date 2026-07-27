<?php

namespace Modules\Content\Filament\Resources;

use Filament\Forms\Components\Component;
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
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Content\Filament\Resources\MenuResource\Pages;
use Modules\Content\Models\Menu;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $navigationIcon = 'heroicon-o-bars-3';

    public static function getNavigationLabel(): string
    {
        return __('admin.menu.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.menu.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.menu.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            FormSection::make()->columns(2)->schema([
                TextInput::make('name')->label(__('admin.common.name'))->required(),
                TextInput::make('handle')->label(__('admin.menu.handle'))->required()->helperText(__('admin.menu.handle_help')),
            ]),

            // The whole tree lives under `tree` (built on fill, flattened on save).
            Repeater::make('tree')
                ->label(__('admin.menu.items'))
                ->schema(static::itemSchema())
                ->collapsible()
                ->reorderable()
                ->itemLabel(fn (array $state) => ($state['label'] ?? __('admin.menu.item')).' ['.($state['type'] ?? 'link').']')
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
                    ->label(__('admin.menu.type'))
                    ->options([
                        'link' => __('admin.menu.type_link'),
                        'dropdown' => __('admin.menu.type_dropdown'),
                        'mega' => __('admin.menu.type_mega'),
                        'footer-column' => __('admin.menu.type_footer_column'),
                    ])
                    ->default('link')->required()->live(),
                TextInput::make('label')->label(__('admin.menu.item_label'))->required(),
                TextInput::make('badge')->label(__('admin.menu.badge'))->placeholder('New / Hot'),
            ]),

            // Destination (shared by link/dropdown/mega top-level)
            FormSection::make()->columns(2)->schema([
                TextInput::make('url')->label(__('admin.menu.url'))->placeholder('/search or https://…'),
                Select::make('collection_id')->label(__('admin.menu.link_collection'))
                    ->options(fn () => static::collectionOptions())->searchable(),
            ]),

            // Dropdown → flat list of links
            Repeater::make('children')
                ->label(__('admin.menu.links'))
                ->visible(fn (Get $get) => in_array($get('type'), ['dropdown', 'footer-column']))
                ->schema(static::linkSchema())
                ->collapsible()->reorderable()
                ->itemLabel(fn (array $state) => $state['label'] ?? __('admin.menu.type_link')),

            // Mega → columns (each with its own links) + optional banners
            Repeater::make('children')
                ->label(__('admin.menu.columns_banners'))
                ->visible(fn (Get $get) => $get('type') === 'mega')
                ->schema([
                    Select::make('type')
                        ->label(__('admin.menu.type'))
                        ->options(['mega-column' => __('admin.menu.type_column'), 'banner' => __('admin.menu.type_banner')])
                        ->default('mega-column')->required()->live(),
                    TextInput::make('label')->label(__('admin.menu.heading_alt')),
                    MediaPicker::make('image', type: 'image')->label(__('admin.menu.banner_image'))
                        ->visible(fn (Get $get) => $get('type') === 'banner'),
                    TextInput::make('url')->label(__('admin.menu.banner_link'))
                        ->visible(fn (Get $get) => $get('type') === 'banner'),
                    Repeater::make('children')
                        ->label(__('admin.menu.links'))
                        ->visible(fn (Get $get) => $get('type') === 'mega-column')
                        ->schema(static::linkSchema())
                        ->collapsible()->reorderable()
                        ->itemLabel(fn (array $state) => $state['label'] ?? __('admin.menu.type_link')),
                ])
                ->collapsible()->reorderable()
                ->itemLabel(fn (array $state) => ($state['label'] ?? __('admin.menu.type_column')).' ['.($state['type'] ?? 'mega-column').']'),
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
            TextInput::make('label')->label(__('admin.menu.item_label'))->required(),
            TextInput::make('url')->label(__('admin.menu.url')),
            Select::make('collection_id')->label(__('admin.menu.collection'))
                ->options(fn () => static::collectionOptions())->searchable(),
            TextInput::make('badge')->label(__('admin.menu.badge'))->placeholder('New'),
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
            TextColumn::make('name')->label(__('admin.common.name')),
            TextColumn::make('handle')->label(__('admin.menu.handle'))->badge(),
            TextColumn::make('items_count')->counts('items')->label(__('admin.menu.items_count')),
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
