<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Modules\Catalog\Services\VariantImages;
use Modules\Checkout\Http\Resources\CartResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A bag line shows the colour the shopper chose.
 *
 * The line's picture is the variant's own main photo — Lunar's
 * `getThumbnail()`, the primary of its variant images — and only falls back to
 * the product's when that colour has no photos of its own (MediaUrl::lineImage).
 * Before, every line showed the product's lead photo, so a shopper who picked
 * the white shirt saw a black one in the bag and at checkout.
 */
class CartLineImageTest extends TestCase
{
    use CreatesStorefrontData;

    private Product $product;

    private Media $lead;

    private Media $white;

    private ProductVariant $whiteVariant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->seedBaseData();

        $this->product = $this->createProduct(['stock' => 10, 'options' => ['Color' => 'Black']]);
        $this->lead = $this->photo('black-lead.jpg');
        $this->white = $this->photo('white.jpg');

        $this->whiteVariant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'LINE-WHITE',
            'tax_class_id' => TaxClass::getDefault()?->id,
            'enabled' => true,
        ]);
        $this->whiteVariant->values()->sync([$this->optionValue($this->product, 'Color', 'White')->id]);
        $this->setStock($this->whiteVariant, 10);
        $this->whiteVariant->prices()->create([
            'price' => 1999,
            'currency_id' => Currency::getDefault()->id,
        ]);

        app(VariantImages::class)->syncVariants([$this->whiteVariant], collect([$this->white]));
    }

    private function photo(string $name): Media
    {
        return $this->product->addMedia(UploadedFile::fake()->image($name, 60, 90))->toMediaCollection('images');
    }

    private function add(ProductVariant $variant): void
    {
        $this->postJson('/api/v1/cart', ['sku_id' => $variant->id, 'quantity' => 1])->assertSuccessful();
    }

    /** @return array<string, string|null> sku => thumbnail */
    private function thumbnails(): array
    {
        $response = $this->getJson('/api/v1/cart')->assertSuccessful();

        return collect($response->json('data.lines') ?? $response->json('lines'))
            ->mapWithKeys(fn (array $line) => [$line['sku'] => $line['thumbnail']])
            ->all();
    }

    public function test_a_line_shows_its_own_colours_photo(): void
    {
        $this->add($this->whiteVariant);

        $this->assertStringContainsString("/{$this->white->id}/conversions/", (string) $this->thumbnails()['LINE-WHITE']);
    }

    public function test_a_colour_without_photos_of_its_own_shows_the_products(): void
    {
        $black = $this->product->variants()->where('sku', '!=', 'LINE-WHITE')->first();
        $this->add($black);

        $this->assertStringContainsString("/{$this->lead->id}/conversions/", (string) $this->thumbnails()[$black->sku]);
    }

    public function test_the_checkout_summary_shows_the_chosen_colour(): void
    {
        $this->add($this->whiteVariant);

        $this->get('/checkout')
            ->assertOk()
            ->assertSee("/{$this->white->id}/conversions/", false);
    }

    /**
     * One query for every line's variant images, however many lines the bag
     * has. Measured on a cart loaded fresh from the database — the one the
     * requests above built is already in memory with everything loaded.
     */
    public function test_a_bag_of_several_lines_loads_variant_images_once(): void
    {
        $this->add($this->whiteVariant);
        $this->add($this->product->variants()->where('sku', '!=', 'LINE-WHITE')->first());
        $this->add($this->createProduct(['stock' => 5])->variants->first());

        $cart = Cart::query()->latest('id')->firstOrFail()->calculate();
        $this->assertCount(3, $cart->lines);

        DB::enableQueryLog();
        DB::flushQueryLog();

        (new CartResource($cart))->toArray(request());

        $queries = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'media_product_variant'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'variant images must be loaded for the whole bag at once, not per line');
    }
}
