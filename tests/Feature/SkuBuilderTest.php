<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;
use Modules\Catalog\Services\PricingService;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SkuBuilderService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Regression guards for the flexible SKU builder (SkuBuilderService::save):
 * the delete-and-recreate strategy must not silently corrupt variant labels
 * (C1) or orphan id-based references like discounts / cart lines (C2).
 */
class SkuBuilderTest extends TestCase
{
    use CreatesStorefrontData;

    /** @return array{0: Product, 1: array, 2: array} product, variables, sku rows */
    private function twoAxisProduct(): array
    {
        $product = $this->createProduct();
        $variables = [
            ['name' => ['en' => 'Color'], 'display_type' => 'text', 'values' => [
                ['name' => ['en' => 'Black']], ['name' => ['en' => 'White']],
            ]],
            ['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
                ['name' => ['en' => 'S']], ['name' => ['en' => 'M']],
            ]],
        ];
        $svc = app(SkuBuilderService::class);
        $combos = $svc->combinations($variables);
        $skus = collect($combos)
            ->map(fn ($c, $i) => ['variants' => $c, 'sku' => 'B-'.$i, 'price' => 1000, 'quantity' => 5])
            ->all();
        $svc->save($product, $variables, $skus);

        return [$product->fresh(), $variables, $skus];
    }

    // ---- C1: variables / skus can't desynchronise ------------------------------

    public function test_reordering_axes_rebinds_combos_so_labels_stay_honest(): void
    {
        $this->seedBaseData();
        [$product, $variables, $skus] = $this->twoAxisProduct();

        // position 1 (combo [0,1]) is "Black, M" before the reorder.
        $before = $product->skus()->orderBy('position')->get()[1];
        $this->assertSame('Black, M', $before->getOption());

        // Swap the axes in `variables` but re-save the SAME posted sku rows (stale
        // posted `variants`). save() must re-derive combos by position, so the
        // stored index never disagrees with the (now swapped) variables.
        $swapped = [$variables[1], $variables[0]];
        $posted = $product->skus()->orderBy('position')->get()
            ->map(fn ($s) => ['variants' => $s->variants, 'sku' => $s->sku, 'price' => $s->price, 'quantity' => $s->quantity])
            ->all();

        app(SkuBuilderService::class)->save($product, $swapped, $posted);

        $after = $product->fresh()->skus()->orderBy('position')->get()[1];
        // Same canonical combo [0,1], but under Size,Color it honestly reads "S, White".
        $this->assertSame([0, 1], $after->variants);
        $this->assertSame('S, White', $after->getOption());

        // optionGroups reflects the new axis order.
        $this->assertSame(['Size', 'Color'], array_keys(app(ProductService::class)->optionGroups($product->fresh())));
    }

    public function test_save_rejects_a_sku_list_that_is_out_of_sync_with_variables(): void
    {
        $this->seedBaseData();
        [$product, $variables, $skus] = $this->twoAxisProduct();

        // Drop an axis (2 combos now) but keep the old 4-row sku payload.
        $this->expectException(ValidationException::class);
        app(SkuBuilderService::class)->save($product, [$variables[0]], $skus);
    }

    public function test_deep_link_resolves_options_with_slugged_names(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $variables = [[
            'name' => ['en' => 'Shoe Size'],
            'display_type' => 'text',
            'values' => [
                ['name' => ['en' => 'Small']],
                ['name' => ['en' => 'Medium']],
            ],
        ]];

        app(SkuBuilderService::class)->save($product, $variables, [
            ['variants' => [0], 'sku' => 'SIZE-S', 'price' => 1000, 'quantity' => 5],
            ['variants' => [1], 'sku' => 'SIZE-M', 'price' => 1000, 'quantity' => 5],
        ]);

        $selected = app(ProductService::class)
            ->resolveSelectedVariant($product->fresh(['skus']), ['shoe-size' => 'medium']);

        $this->assertSame('SIZE-M', $selected->sku);
    }

    // ---- C2: id-based references follow the sku code across a re-save ----------

    public function test_variant_scoped_discount_and_cart_line_follow_the_sku_across_a_resave(): void
    {
        $this->seedBaseData();
        [$product, $variables, $skus] = $this->twoAxisProduct();

        $sku = ProductSku::where('sku', 'B-0')->first();
        $oldId = $sku->id;
        $morph = $sku->getMorphClass();

        $discount = Discount::create([
            'name' => 'Variant deal', 'handle' => 'vd'.uniqid(),
            'type' => Discount::class, 'starts_at' => now()->subDay(), 'data' => [],
        ]);
        DB::table('lunar_discountables')->insert([
            'discount_id' => $discount->id, 'discountable_type' => $morph,
            'discountable_id' => $oldId, 'type' => 'limitation',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $cart = Cart::create(['currency_id' => Currency::getDefault()->id, 'channel_id' => Channel::getDefault()->id]);
        DB::table('lunar_cart_lines')->insert([
            'cart_id' => $cart->id, 'purchasable_type' => $morph, 'purchasable_id' => $oldId,
            'quantity' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Re-save the product → SKUs are recreated with fresh ids.
        app(SkuBuilderService::class)->save($product, $variables, $skus);
        $newId = ProductSku::where('sku', 'B-0')->first()->id;

        $this->assertNotSame($oldId, $newId, 'the sku id should change on re-save');
        $this->assertSame($newId, (int) DB::table('lunar_discountables')->where('discount_id', $discount->id)->value('discountable_id'));
        $this->assertSame($newId, (int) DB::table('lunar_cart_lines')->where('cart_id', $cart->id)->value('purchasable_id'));
    }

    // ---- H1: a zero-priced SKU must not be sellable for free --------------------

    public function test_a_zero_price_sku_gets_no_base_price_row_and_is_unpriced(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $variables = [['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
            ['name' => ['en' => 'S']], ['name' => ['en' => 'M']],
        ]]];
        $svc = app(SkuBuilderService::class);
        $combos = $svc->combinations($variables);
        $svc->save($product, $variables, [
            ['variants' => $combos[0], 'sku' => 'Z-S', 'price' => 1000, 'quantity' => 5],
            ['variants' => $combos[1], 'sku' => 'Z-M', 'price' => 0, 'quantity' => 5], // blank/zero
        ]);

        $priced = ProductSku::where('sku', 'Z-S')->first();
        $zero = ProductSku::where('sku', 'Z-M')->first();

        $this->assertSame(1, $priced->prices()->count());
        $this->assertSame(0, $zero->prices()->count(), 'a 0 price must not create a matchable base price row');
        $this->assertNull(app(PricingService::class)->matchedPrice($zero));
    }

    // ---- H4: the default SKU + headline price must be a published one -----------

    public function test_a_disabled_sku_is_never_the_default_or_the_headline_price(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();
        $variables = [['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
            ['name' => ['en' => 'S']], ['name' => ['en' => 'M']],
        ]]];
        $svc = app(SkuBuilderService::class);
        $combos = $svc->combinations($variables);

        // Position 0 (S) is disabled but flagged default and pricier; M is published.
        $svc->save($product, $variables, [
            ['variants' => $combos[0], 'sku' => 'D-S', 'price' => 5000, 'quantity' => 5, 'status' => 'disabled', 'is_default' => true],
            ['variants' => $combos[1], 'sku' => 'D-M', 'price' => 2000, 'quantity' => 5, 'status' => 'published'],
        ]);

        $default = $product->fresh()->skus()->where('is_default', true)->first();
        $this->assertSame('D-M', $default->sku);
        $this->assertSame('published', $default->status);
        $this->assertFalse($product->fresh()->skus()->where('is_default', true)->where('status', 'disabled')->exists());

        // Headline price reflects the published M (20.00), not the disabled S (50.00).
        $this->assertStringContainsString('20', (string) app(PricingService::class)->displayPrice($product->fresh()));
    }

    // ---- L2: the sku code is unique at the DB level ----------------------------

    public function test_duplicate_live_sku_codes_are_rejected_by_the_database(): void
    {
        $this->seedBaseData();
        [$product] = $this->twoAxisProduct(); // creates B-0..B-3

        // A second live row reusing an existing code must be refused by the unique
        // index — the last line of defence behind the PHP pre-check.
        $this->expectException(QueryException::class);
        ProductSku::create([
            'product_id' => $product->id, 'sku' => 'B-0', 'variants' => [],
            'quantity' => 1, 'price' => 1, 'status' => 'published',
        ]);
    }

    public function test_resaving_a_product_reuses_its_codes_without_tripping_the_unique(): void
    {
        $this->seedBaseData();
        [$product, $variables, $skus] = $this->twoAxisProduct();

        // save() force-deletes then recreates the same codes in one transaction —
        // the unique index must not block this legitimate rebuild.
        app(SkuBuilderService::class)->save($product, $variables, $skus);

        $this->assertSame(4, $product->fresh()->skus()->count());
        $this->assertSame(['B-0', 'B-1', 'B-2', 'B-3'], $product->fresh()->skus()->orderBy('position')->pluck('sku')->all());
    }
}
