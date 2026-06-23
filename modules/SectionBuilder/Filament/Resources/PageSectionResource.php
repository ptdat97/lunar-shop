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

    protected static ?string $navigationLabel = 'Page Sections';

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    protected static ?string $modelLabel = 'Section';

    public static function form(Form $form): Form
    {
        return $form->schema([
            FormSection::make()->columns(3)->schema([
                TextInput::make('page_handle')
                    ->label('Page')
                    ->default('home')
                    ->required(),

                Select::make('type')
                    ->label('Section type')
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

            FormSection::make('Hero slides')
                ->visible(fn (Get $get) => $get('type') === 'hero-slider')
                ->schema([
                    Repeater::make('settings.slides')
                        ->label('Slides')
                        ->schema([
                            FileUpload::make('image')->image()->disk('media')->directory('sections/hero')
                                ->helperText('Upload a slide image (stored in public/media).')->columnSpanFull(),
                            TextInput::make('title'),
                            Textarea::make('subtitle')->rows(2),
                            TextInput::make('button_text'),
                            TextInput::make('button_url')->default('/search'),
                        ])
                        ->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['title'] ?? 'Slide'),
                ]),

            FormSection::make('Icon boxes')
                ->visible(fn (Get $get) => $get('type') === 'iconbox')
                ->schema([
                    Repeater::make('settings.items')
                        ->label('Items')
                        ->schema([
                            TextInput::make('icon')->default('icon-sealCheck')
                                ->helperText('Modave icon class, e.g. icon-shipping.'),
                            TextInput::make('heading'),
                            TextInput::make('text'),
                        ])
                        ->columns(3)->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['heading'] ?? 'Item'),
                ]),

            FormSection::make('Lookbook slides')
                ->visible(fn (Get $get) => $get('type') === 'lookbook')
                ->schema([
                    Repeater::make('settings.slides')
                        ->label('Slides')
                        ->schema([
                            FileUpload::make('banner')->label('Banner image')->image()->disk('media')->directory('sections/lookbook')->columnSpanFull(),
                            FileUpload::make('pin_image')->label('Pin product image')->image()->disk('media')->directory('sections/lookbook'),
                            TextInput::make('position')->label('Pin position')->placeholder('position3 / position5'),
                            TextInput::make('pin_title')->label('Product title'),
                            TextInput::make('pin_price')->label('Price'),
                            TextInput::make('pin_url')->label('Link')->default('/search'),
                        ])
                        ->columns(2)->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['pin_title'] ?? 'Slide'),
                ]),

            FormSection::make('Testimonials')
                ->visible(fn (Get $get) => $get('type') === 'testimonial')
                ->schema([
                    TextInput::make('settings.heading')->label('Heading'),
                    TextInput::make('settings.subheading')->label('Subheading'),
                    Repeater::make('settings.items')
                        ->label('Reviews')
                        ->schema([
                            FileUpload::make('image')->label('Review image')->image()->disk('media')->directory('sections/testimonial'),
                            FileUpload::make('avatar')->label('Avatar')->image()->disk('media')->directory('sections/testimonial'),
                            TextInput::make('author'),
                            TextInput::make('rating')->numeric()->minValue(1)->maxValue(5)->default(5),
                            Textarea::make('text')->rows(3)->columnSpanFull(),
                            TextInput::make('product_name')->label('Product'),
                            TextInput::make('product_price')->label('Product price'),
                        ])
                        ->columns(2)->collapsible()->reorderable()->itemLabel(fn (array $state) => $state['author'] ?? 'Review'),
                ]),

            FormSection::make('Settings')
                ->visible(fn (Get $get) => in_array($get('type'), ['category-grid', 'product-tabs', 'instagram']))
                ->columns(2)
                ->schema([
                    TextInput::make('settings.heading')->label('Heading')
                        ->visible(fn (Get $get) => in_array($get('type'), ['category-grid', 'instagram'])),
                    TextInput::make('settings.subheading')->label('Subheading')
                        ->visible(fn (Get $get) => $get('type') === 'instagram'),
                    TextInput::make('settings.limit')->label('Item limit')->numeric()->minValue(1)
                        ->visible(fn (Get $get) => in_array($get('type'), ['category-grid', 'product-tabs'])),
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
                    ->badge()
                    ->formatStateUsing(fn (string $state) => PageSection::TYPES[$state] ?? $state),
                TextColumn::make('page_handle')->label('Page')->badge()->color('gray'),
                ToggleColumn::make('enabled'),
                TextColumn::make('updated_at')->since()->label('Updated')->toggleable(),
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
