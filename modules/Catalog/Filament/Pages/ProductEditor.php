<?php

namespace Modules\Catalog\Filament\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Lunar\Admin\Support\Forms\Components\Attributes;
use Lunar\Admin\Support\Forms\Components\Tags as TagsComponent;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\Tag;
use Modules\Catalog\Filament\Resources\ProductResource;
use Modules\Catalog\Filament\Widgets\SimpleVariantsWidget;
use Modules\Catalog\Services\Admin\ProductEditorService;

/**
 * Single-page product editor (BeikeShop-style): basic data + description +
 * SEO (Lunar attribute groups), drag-drop gallery, simple-product SKU/price/
 * stock, status, brand/type/tags, collections, related products and the URL
 * slug — all in one form, with the Size/Color variants matrix embedded below
 * as a footer widget. Replaces Lunar's EditProduct plus its nine sub-pages
 * (media, pricing, inventory, variants, urls, collections, associations, …).
 *
 * Column data saves through the normal Filament pipeline; everything that
 * lives on other aggregates goes through ProductEditorService.
 */
class ProductEditor extends BaseEditRecord
{
    protected static string $resource = ProductResource::class;

    public static bool $formActionsAreSticky = true;

    /** Editor-only form state captured during save for the service layer. */
    protected array $editorState = [];

    protected const EDITOR_KEYS = ['sku', 'price', 'stock', 'slugs', 'collection_ids', 'related_ids'];

    public function getTitle(): string
    {
        return __('admin.editor.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.editor.nav');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::basic-information');
    }

    public function form(Form $form): Form
    {
        $currency = Currency::getDefault();

        return $form
            ->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Group::make([
                        // Product attribute groups: Details (name, description)
                        // and SEO (meta title/description), each translatable.
                        Attributes::make(),

                        Forms\Components\Section::make(__('admin.editor.simple'))
                            ->description(__('admin.editor.simple_hint'))
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('sku')
                                    ->label(__('admin.variants.sku'))
                                    ->required()
                                    ->unique(
                                        table: (new ProductVariant)->getTable(),
                                        column: 'sku',
                                        ignorable: fn (Model $record) => $record->variants->first(),
                                    ),
                                Forms\Components\TextInput::make('price')
                                    ->label(__('admin.variants.price'))
                                    ->numeric()
                                    ->prefix($currency->code)
                                    ->rules(["decimal:0,{$currency->decimal_places}"])
                                    ->required(),
                                Forms\Components\TextInput::make('stock')
                                    ->label(__('admin.variants.stock'))
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->visible(fn (?Model $record) => $record && $record->variants()->count() === 1),

                        // Variant-level attributes for option-less products
                        // (mirrors Lunar's stock edit page behaviour).
                        Attributes::make()
                            ->using(ProductVariant::class)
                            ->relationship('variant')
                            ->hidden(fn (ProductContract $record) => $record->hasVariants),
                    ])->columnSpan(2),

                    Forms\Components\Group::make([
                        Forms\Components\Section::make(__('admin.editor.status'))
                            ->schema([
                                Forms\Components\Radio::make('status')
                                    ->hiddenLabel()
                                    ->options([
                                        'published' => __('lunarpanel::product.form.status.options.published.label'),
                                        'draft' => __('lunarpanel::product.form.status.options.draft.label'),
                                    ])
                                    ->descriptions([
                                        'published' => __('lunarpanel::product.form.status.options.published.description'),
                                        'draft' => __('lunarpanel::product.form.status.options.draft.description'),
                                    ])
                                    ->required(),
                            ]),

                        Forms\Components\Section::make(__('admin.editor.organisation'))
                            ->schema([
                                Forms\Components\Select::make('brand_id')
                                    ->label(__('lunarpanel::product.form.brand.label'))
                                    ->relationship('brand', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                    ]),
                                Forms\Components\Select::make('product_type_id')
                                    ->label(__('lunarpanel::product.form.producttype.label'))
                                    ->relationship('productType', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),
                                TagsComponent::make('tags')
                                    ->label(__('lunarpanel::product.form.tags.label'))
                                    ->suggestions(Tag::all()->pluck('value')->all())
                                    ->splitKeys(['Tab', ',']),
                            ]),

                        Forms\Components\Section::make(__('admin.editor.collections'))
                            ->schema([
                                Forms\Components\CheckboxList::make('collection_ids')
                                    ->hiddenLabel()
                                    ->options(fn () => static::collectionOptions())
                                    ->searchable()
                                    ->bulkToggleable(),
                            ]),

                        Forms\Components\Section::make(__('admin.editor.related'))
                            ->description(__('admin.editor.related_hint'))
                            ->schema([
                                Forms\Components\Select::make('related_ids')
                                    ->hiddenLabel()
                                    ->multiple()
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn (string $search) => static::productSearchResults($search)
                                    )
                                    ->getOptionLabelsUsing(
                                        fn (array $values) => static::productLabels($values)
                                    ),
                            ]),

                        Forms\Components\Section::make(__('admin.editor.url'))
                            ->description(__('admin.editor.url_hint'))
                            ->schema(static::slugInputs()),
                    ])->columnSpan(1),
                ]),
            ])
            ->columns(1);
    }

    /**
     * One slug input per store language (Lunar's multilingual URLs: one
     * default URL row per language). Only the default language is required.
     *
     * @return array<int, Forms\Components\Component>
     */
    protected static function slugInputs(): array
    {
        return Language::query()
            ->orderByDesc('default')
            ->get()
            ->map(fn (Language $language) => Forms\Components\TextInput::make("slugs.{$language->code}")
                ->label($language->name.' ('.strtoupper($language->code).')')
                ->prefix('/products/')
                ->required($language->default)
                ->maxLength(255))
            ->all();
    }

    /** @return array<int, string> */
    protected static function collectionOptions(): array
    {
        return LunarCollection::query()
            ->with('group')
            ->get()
            ->mapWithKeys(function (LunarCollection $collection) {
                $crumbs = $collection->breadcrumb->implode(' › ');
                $name = $collection->translateAttribute('name');

                return [$collection->id => $crumbs ? "{$crumbs} › {$name}" : $name];
            })
            ->all();
    }

    /** @return array<int, string> */
    protected static function productSearchResults(string $search): array
    {
        return get_search_builder(Product::modelClass(), $search)
            ->get()
            ->mapWithKeys(fn ($record) => [$record->getKey() => (string) $record->translateAttribute('name')])
            ->all();
    }

    /** @return array<int, string> */
    protected static function productLabels(array $values): array
    {
        return Product::query()
            ->findMany($values)
            ->mapWithKeys(fn ($record) => [$record->getKey() => (string) $record->translateAttribute('name')])
            ->all();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge(
            $data,
            app(ProductEditorService::class)->fill($this->getRecord())
        );

        return parent::mutateFormDataBeforeFill($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->editorState = Arr::only($data, self::EDITOR_KEYS);

        $record = parent::handleRecordUpdate($record, Arr::except($data, self::EDITOR_KEYS));

        app(ProductEditorService::class)->save($record, $this->editorState);

        return $record;
    }

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make()->databaseTransaction(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getDefaultFooterWidgets(): array
    {
        return [
            SimpleVariantsWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->getRecord()];
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}
