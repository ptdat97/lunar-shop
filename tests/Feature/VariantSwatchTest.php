<?php

namespace Tests\Feature;

use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\SkuBuilderService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

class VariantSwatchTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_option_groups_expose_text_color_and_image_display_types(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        $variables = [
            ['name' => ['en' => 'Color'], 'display_type' => 'color', 'values' => [
                ['name' => ['en' => 'Black'], 'color' => '#111111'],
            ]],
            ['name' => ['en' => 'Pattern'], 'display_type' => 'image', 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => 'variant-swatches/s.jpg'],
            ]],
            ['name' => ['en' => 'Size'], 'display_type' => 'text', 'values' => [
                ['name' => ['en' => 'S']],
            ]],
        ];
        $svc = app(SkuBuilderService::class);
        $combos = $svc->combinations($variables);
        $skus = collect($combos)->map(fn ($c, $i) => ['variants' => $c, 'sku' => 'SW-'.$i, 'price' => 1000, 'quantity' => 5, 'status' => 'published'])->all();
        $svc->save($product, $variables, $skus);

        $groups = app(ProductService::class)->optionGroups($product->fresh());

        $this->assertSame('color', $groups['Color']['display_type']);
        $this->assertSame('#111111', $groups['Color']['values'][0]['color']);
        $this->assertNull($groups['Color']['values'][0]['image']);

        $this->assertSame('image', $groups['Pattern']['display_type']);
        $this->assertSame('/media/variant-swatches/s.jpg', $groups['Pattern']['values'][0]['image']);

        $this->assertSame('text', $groups['Size']['display_type']);
        $this->assertNull($groups['Size']['values'][0]['color']);
        $this->assertNull($groups['Size']['values'][0]['image']);
    }

    public function test_legacy_is_image_flag_maps_to_image_display_type(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['variables' => [
            ['name' => ['en' => 'Pattern'], 'isImage' => true, 'values' => [
                ['name' => ['en' => 'Stripe'], 'image' => 'https://cdn.example.com/s.png'],
            ]],
        ]]);

        $groups = app(ProductService::class)->optionGroups($product->fresh());

        $this->assertSame('image', $groups['Pattern']['display_type']);
        $this->assertSame('https://cdn.example.com/s.png', $groups['Pattern']['values'][0]['image']);
    }
}
