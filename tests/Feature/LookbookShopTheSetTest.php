<?php

namespace Tests\Feature;

use Modules\Content\Models\Lookbook;
use Modules\Content\Models\LookbookItem;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * "Shop the set" must hand the cart endpoint ids it can actually resolve.
 *
 * It did not. The page rendered `$product->variants->first()->id` — Lunar's own
 * `ProductVariant` — while `POST /api/v1/cart` resolves whatever it is given as
 * a `ProductVariant`. Two id spaces, both starting at 1, so every id matched *some*
 * SKU: measured on the dev catalogue, variant 13 belongs to product 13 while SKU
 * 13 belongs to product 2. The button added a stranger's product and reported
 * success, because the request really did succeed.
 *
 * The shop sells `ProductVariant`; Lunar variants are vestigial here (0 order lines,
 * 0 cart lines reference them). So the page must read `skus`, which is what the
 * lookbook query eager-loads anyway.
 */
class LookbookShopTheSetTest extends TestCase
{
    use CreatesStorefrontData;

    private function lookbookWith(array $products): Lookbook
    {
        $lookbook = Lookbook::create([
            'title' => 'Autumn Set',
            'slug' => 'autumn-set',
            'description' => 'Two pieces.',
            'published' => true,
        ]);

        foreach ($products as $sort => $product) {
            LookbookItem::create([
                'lookbook_id' => $lookbook->id,
                'product_id' => $product->id,
                'sort' => $sort,
            ]);
        }

        return $lookbook;
    }

    public function test_the_set_button_carries_sku_ids_not_variant_ids(): void
    {
        $this->seedBaseData();

        $a = $this->createProduct(['name' => 'Coat', 'sku' => 'COAT-1']);
        $b = $this->createProduct(['name' => 'Scarf', 'sku' => 'SCARF-1']);
        $lookbook = $this->lookbookWith([$a, $b]);

        $expected = [$a->variants->first()->id, $b->variants->first()->id];

        $html = $this->get("/lookbooks/{$lookbook->slug}")->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-sku-ids="[\d,]+"/', $html, 'the set button is missing');

        preg_match('/data-sku-ids="([\d,]+)"/', $html, $m);
        $rendered = array_map('intval', explode(',', $m[1]));

        $this->assertSame($expected, $rendered);
    }

    /**
     * The decisive one: every id on the button must resolve to a SKU belonging
     * to a product in the set. This is what the variant-id version failed —
     * the ids resolved, just to the wrong products.
     */
    public function test_every_id_resolves_to_a_sku_of_a_product_in_the_set(): void
    {
        $this->seedBaseData();

        $a = $this->createProduct(['name' => 'Coat', 'sku' => 'COAT-1']);
        $b = $this->createProduct(['name' => 'Scarf', 'sku' => 'SCARF-1']);
        $lookbook = $this->lookbookWith([$a, $b]);

        $html = $this->get("/lookbooks/{$lookbook->slug}")->assertOk()->getContent();
        preg_match('/data-sku-ids="([\d,]+)"/', $html, $m);

        foreach (explode(',', $m[1]) as $id) {
            $this->postJson('/api/v1/cart', ['sku_id' => (int) $id, 'quantity' => 1])
                ->assertSuccessful();
        }

        $cart = $this->getJson('/api/v1/cart')->assertSuccessful()->json('data');
        $inCart = collect($cart['lines'])->pluck('name')->sort()->values()->all();

        $this->assertSame(['Coat', 'Scarf'], $inCart);
    }
}
