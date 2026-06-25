<?php

namespace Modules\SectionBuilder\Filament\Resources;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Modules\SectionBuilder\Filament\Resources\PageSectionResource\Pages;
use Modules\SectionBuilder\Models\PageSection;
use Modules\SectionBuilder\Support\SectionSchemas;

class PageSectionResource extends Resource
{
    protected static ?string $model = PageSection::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('admin.section.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.section.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.section.plural');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            FormSection::make()->columns(3)->schema([
                TextInput::make('page_handle')
                    ->label(__('admin.section.page_handle'))
                    ->default('home')
                    ->required(),

                Select::make('type')
                    ->label(__('admin.section.type'))
                    ->options(PageSection::TYPES)
                    ->required()
                    ->live()
                    // When the type changes and there are no settings yet,
                    // pre-fill the template defaults so content is ready to edit.
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if ($state && blank($get('settings'))) {
                            $set('settings', SectionSchemas::defaults($state));
                        }
                    }),

                Toggle::make('enabled')->default(true)->inline(false),
            ]),

            // --- Per-type settings (only the relevant block shows) ---

            FormSection::make(__('admin.section.hero_slides'))
                ->visible(fn (Get $get) => $get('type') === 'hero-slider')
                ->schema([
                    Repeater::make('settings.slides')
                        ->label(__('admin.section.slides'))
                        ->schema([
                            FileUpload::make('image')->image()->disk('media')->directory('sections/hero')
                                ->helperText(__('admin.section.hero_image_help'))->columnSpanFull(),
                            TextInput::make('title')->label(__('admin.common.title')),
                            Textarea::make('subtitle')->label(__('admin.common.subheading'))->rows(2),
                            TextInput::make('button_text')->label(__('admin.section.button_text')),
                            TextInput::make('button_url')->label(__('admin.section.button_url'))->default('/search'),
                        ])
                        ->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['title'] ?? __('admin.section.slide')),
                ]),

            FormSection::make(__('admin.section.iconboxes'))
                ->visible(fn (Get $get) => $get('type') === 'iconbox')
                ->schema([
                    Repeater::make('settings.items')
                        ->label(__('admin.section.items'))
                        ->schema([
                            TextInput::make('icon')->default('icon-sealCheck')
                                ->helperText(__('admin.section.icon_help')),
                            TextInput::make('heading')->label(__('admin.common.heading')),
                            TextInput::make('text')->label(__('admin.section.text')),
                        ])
                        ->columns(3)->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['heading'] ?? __('admin.section.item')),
                ]),

            FormSection::make(__('admin.section.lookbook_slides'))
                ->visible(fn (Get $get) => $get('type') === 'lookbook')
                ->schema([
                    Repeater::make('settings.slides')
                        ->label(__('admin.section.slides'))
                        ->schema([
                            FileUpload::make('banner')->label(__('admin.section.banner_image'))->image()->disk('media')->directory('sections/lookbook')->columnSpanFull(),
                            FileUpload::make('pin_image')->label(__('admin.section.pin_image'))->image()->disk('media')->directory('sections/lookbook'),
                            TextInput::make('position')->label(__('admin.section.pin_position'))->placeholder('position3 / position5'),
                            TextInput::make('pin_title')->label(__('admin.section.product_title')),
                            TextInput::make('pin_price')->label(__('admin.section.price')),
                            TextInput::make('pin_url')->label(__('admin.section.link'))->default('/search'),
                        ])
                        ->columns(2)->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['pin_title'] ?? __('admin.section.slide')),
                ]),

            FormSection::make(__('admin.section.testimonials'))
                ->visible(fn (Get $get) => $get('type') === 'testimonial')
                ->schema([
                    TextInput::make('settings.heading')->label(__('admin.common.heading')),
                    TextInput::make('settings.subheading')->label(__('admin.common.subheading')),
                    Repeater::make('settings.items')
                        ->label(__('admin.section.reviews'))
                        ->schema([
                            FileUpload::make('image')->label(__('admin.section.review_image'))->image()->disk('media')->directory('sections/testimonial'),
                            FileUpload::make('avatar')->label(__('admin.section.avatar'))->image()->disk('media')->directory('sections/testimonial'),
                            TextInput::make('author')->label(__('admin.section.author')),
                            TextInput::make('rating')->label(__('admin.section.rating'))->numeric()->minValue(1)->maxValue(5)->default(5),
                            Textarea::make('text')->label(__('admin.section.text'))->rows(3)->columnSpanFull(),
                            TextInput::make('product_name')->label(__('admin.section.product')),
                            TextInput::make('product_price')->label(__('admin.section.product_price')),
                        ])
                        ->columns(2)->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['author'] ?? __('admin.section.review')),
                ]),

            FormSection::make(__('admin.section.settings'))
                ->visible(fn (Get $get) => in_array($get('type'), ['category-grid', 'product-tabs', 'instagram']))
                ->columns(2)
                ->schema([
                    TextInput::make('settings.heading')->label(__('admin.common.heading'))
                        ->visible(fn (Get $get) => in_array($get('type'), ['category-grid', 'instagram'])),
                    TextInput::make('settings.subheading')->label(__('admin.common.subheading'))
                        ->visible(fn (Get $get) => $get('type') === 'instagram'),
                    TextInput::make('settings.limit')->label(__('admin.section.item_limit'))->numeric()->minValue(1)
                        ->visible(fn (Get $get) => in_array($get('type'), ['category-grid', 'product-tabs'])),
                ]),

            FormSection::make(__('admin.section.promotion_section'))
                ->visible(fn (Get $get) => $get('type') === 'promotion-slider')
                ->columns(2)
                ->schema([
                    TextInput::make('settings.heading')->label(__('admin.common.heading')),
                    TextInput::make('settings.subheading')->label(__('admin.common.subheading')),
                    TextInput::make('settings.limit')->label(__('admin.section.promotion_count'))
                        ->numeric()->minValue(1)->maxValue(50)->default(12)
                        ->helperText(__('admin.section.promotion_count_help')),
                    Select::make('settings.promotion')->label(__('admin.section.promotion_pin'))
                        ->options(fn () => app(\Modules\Promotion\Services\PromotionService::class)
                            ->displayablePromotions()
                            ->mapWithKeys(fn ($d) => [$d->handle => $d->name])
                            ->all())
                        ->placeholder(__('admin.section.promotion_pin_placeholder'))
                        ->helperText(__('admin.section.promotion_pin_help')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('sort')->label('#')->sortable(),
                TextColumn::make('type')
                    ->label(__('admin.section.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PageSection::TYPES[$state] ?? $state),
                TextColumn::make('page_handle')->label(__('admin.section.page_handle'))->badge()->color('gray'),
                ToggleColumn::make('enabled')->label(__('admin.common.enabled')),
                TextColumn::make('updated_at')->since()->label(__('admin.section.updated'))->toggleable(),
            ])
            ->actions([
                // Edit opens in a slide-over modal — no separate page.
                EditAction::make()->slideOver(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        // Single page: table + modal create/edit, all in one screen.
        return [
            'index' => Pages\ManagePageSections::route('/'),
        ];
    }
}
