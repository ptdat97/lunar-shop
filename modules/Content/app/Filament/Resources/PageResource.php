<?php

namespace Modules\Content\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
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
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Content\Filament\Resources\PageResource\Pages\CreatePage;
use Modules\Content\Filament\Resources\PageResource\Pages\EditPage;
use Modules\Content\Filament\Resources\PageResource\Pages\ListPages;
use Modules\Content\Models\Page;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.page.section_details'))
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
                    ])
                    ->columns(3),

                Section::make(__('admin.page.section_featured'))
                    ->description(__('admin.page.featured_pick'))
                    ->schema([
                        MediaPicker::make('featured_image', type: 'image')
                            ->label(__('admin.page.featured_image')),
                    ]),

                Section::make(__('admin.page.section_content'))
                    ->schema([
                        RichEditor::make('content')
                            ->label(__('admin.common.content'))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('admin.common.seo'))
                    ->schema([
                        TextInput::make('meta_title')
                            ->label(__('admin.common.meta_title'))
                            ->maxLength(255),
                        Textarea::make('meta_description')
                            ->label(__('admin.common.meta_description'))
                            ->maxLength(500),
                        KeyValue::make('og_data')
                            ->keyLabel(__('admin.common.property'))
                            ->valueLabel(__('admin.common.value')),
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
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable(),
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
