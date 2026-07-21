<?php

namespace Modules\Content\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Content\Filament\Resources\PageResource\Pages\CreatePage;
use Modules\Content\Filament\Resources\PageResource\Pages\EditPage;
use Modules\Content\Filament\Resources\PageResource\Pages\ListPages;
use Modules\Content\Models\Page;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.page.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.page.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('admin.page.section_details'))
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
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('admin.page.section_featured'))
                    ->description(__('admin.page.featured_pick'))
                    ->schema([
                        MediaPicker::make('featured_image', type: 'image')
                            ->label(__('admin.page.featured_image')),
                    ]),

                Forms\Components\Section::make(__('admin.page.section_content'))
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label(__('admin.common.content'))
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('admin.common.seo'))
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label(__('admin.common.meta_title'))
                            ->maxLength(255),
                        Forms\Components\Textarea::make('meta_description')
                            ->label(__('admin.common.meta_description'))
                            ->maxLength(500),
                        Forms\Components\KeyValue::make('og_data')
                            ->keyLabel(__('admin.common.property'))
                            ->valueLabel(__('admin.common.value')),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable(),
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
