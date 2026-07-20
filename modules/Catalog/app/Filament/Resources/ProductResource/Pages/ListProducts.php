<?php

namespace Modules\Catalog\Filament\Resources\ProductResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts as LunarListProducts;
use Lunar\Facades\DB;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Modules\Catalog\Filament\Resources\ProductResource;
use Modules\Catalog\Models\ProductSku;

/**
 * Swapped in for Lunar's ListProducts (ModulesServiceProvider $swaps) to
 * simplify the "add product" flow.
 *
 * Kept as a modal action on the list (no separate Create page): the SME just
 * enters name + type + SKU, optionally a price and whether to publish, and
 * lands straight on the edit page. Improvements over Lunar's modal:
 *   - Vietnamese labels + a short description matching the editor.
 *   - Price is optional (set-later is common); status is chosen up front
 *     instead of always forcing a draft.
 *   - Wider modal so the two-column grid breathes.
 * The table, tabs and filters stay Lunar's — only the create action changes.
 */
class ListProducts extends LunarListProducts
{
    protected static string $resource = ProductResource::class;

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('admin.create.label'))
                ->modalHeading(__('admin.create.heading'))
                ->modalDescription(__('admin.create.description'))
                ->modalWidth(MaxWidth::TwoExtraLarge)
                ->modalSubmitActionLabel(__('admin.create.submit'))
                ->createAnother(false)
                ->form(static::createActionFormInputs())
                ->using(fn (array $data, string $model) => static::createRecord($data, $model))
                ->successRedirectUrl(fn (Model $record): string => ProductResource::getUrl('edit', [
                    'record' => $record,
                ])),
        ];
    }

    public static function createActionFormInputs(): array
    {
        $currency = Currency::getDefault();

        return [
            Grid::make(2)->schema([
                ProductResource::getBaseNameFormComponent()
                    ->label(__('admin.create.name'))
                    ->columnSpanFull(),

                ProductResource::getProductTypeFormComponent()
                    ->label(__('admin.create.type'))
                    ->required(),

                Forms\Components\TextInput::make('sku')
                    ->label(__('admin.create.sku'))
                    ->required()
                    ->unique(table: (new ProductSku)->getTable(), column: 'sku'),

                // Price optional here — the editor / variants matrix is the
                // canonical place to set it; blank stores a 0 base price.
                Forms\Components\TextInput::make('base_price')
                    ->label(__('admin.create.price'))
                    ->helperText(__('admin.create.price_hint'))
                    ->numeric()
                    ->prefix($currency->code)
                    ->rules(["decimal:0,{$currency->decimal_places}"]),

                Forms\Components\Toggle::make('publish')
                    ->label(__('admin.create.status'))
                    ->helperText(__('admin.create.status_hint'))
                    ->default(false)
                    ->columnSpanFull(),
            ]),
        ];
    }

    public static function createRecord(array $data, string $model): Model
    {
        $currency = Currency::getDefault();

        // Field type for the name attribute; fall back to TranslatedText if the
        // attribute row is missing (matches how products are stored anyway).
        $nameAttribute = Attribute::whereAttributeType($model::morphName())
            ->whereHandle('name')
            ->first()
            ?->type ?? TranslatedText::class;

        // Product + default SKU + base price write three related tables —
        // wrap so a mid-way failure rolls back instead of leaving an orphan.
        // Every product starts as a single-SKU (simple) product; the variant
        // builder later expands it into a matrix if the merchant defines options.
        return DB::transaction(function () use ($data, $model, $currency, $nameAttribute) {
            $product = $model::create([
                'status' => ! empty($data['publish']) ? 'published' : 'draft',
                'product_type_id' => $data['product_type_id'],
                'attribute_data' => [
                    'name' => new $nameAttribute($data['name']),
                ],
            ]);

            // Lunar's Product is fully $guarded — set the flexible-variant blob
            // directly (empty for a simple product) rather than mass-assigning.
            $product->variables = [];
            $product->save();

            $price = (int) bcmul((string) ($data['base_price'] ?? 0), (string) $currency->factor);

            $sku = ProductSku::create([
                'product_id' => $product->id,
                'tax_class_id' => TaxClass::getDefault()->id,
                'sku' => $data['sku'],
                'variants' => [],
                'price' => $price,
                'is_default' => true,
                'status' => 'published',
            ]);

            $sku->prices()->create([
                'min_quantity' => 1,
                'currency_id' => $currency->id,
                'price' => $price,
            ]);

            return $product;
        });
    }
}
