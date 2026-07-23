<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\Models\Product;
use Modules\Catalog\Services\SkuBuilderService;

/**
 * Builds the flexible SKU layer (lunar_product_skus + the product's `variables`
 * blob) for the demo catalog.
 *
 * The other catalog seeders create Lunar ProductVariant rows, but the storefront
 * reads SKUs: ProductService::findBySlug eager-loads `skus`, resolveSelectedVariant
 * picks one from them, and optionGroups() derives the colour/size buttons from
 * `variables`. Without this seeder every demo product renders with no option
 * picker, no stock line and a disabled add-to-cart button.
 *
 * Each product gets a colour x size matrix so the demo exercises the real
 * features: multi-axis combinations, a colour swatch axis (hex) next to a text
 * axis, per-SKU price/stock/weight, an origin_price on some rows so the sale
 * badge renders, and one disabled SKU so the storefront's published filter is
 * visibly doing something.
 *
 * Idempotent: SkuBuilderService::save() is an authoritative rewrite, so
 * re-running produces the same matrix rather than duplicating it.
 */
class ProductSkuMatrixSeeder extends Seeder
{
    /**
     * Colour axis: label + swatch hex. Kept small so the Cartesian product stays
     * a sane size (3 colours x 4 sizes = 12 SKUs per product).
     *
     * @var list<array{en: string, vi: string, hex: string}>
     */
    protected const COLORS = [
        ['en' => 'Black', 'vi' => 'Đen', 'hex' => '#1a1a1a'],
        ['en' => 'White', 'vi' => 'Trắng', 'hex' => '#f5f5f5'],
        ['en' => 'Navy', 'vi' => 'Xanh navy', 'hex' => '#1f2a44'],
    ];

    /** @var list<array{en: string, vi: string}> */
    protected const SIZES = [
        ['en' => 'S', 'vi' => 'S'],
        ['en' => 'M', 'vi' => 'M'],
        ['en' => 'L', 'vi' => 'L'],
        ['en' => 'XL', 'vi' => 'XL'],
    ];

    /** Per-size weight in grams, so shipping estimates differ across the run. */
    protected const WEIGHT_BY_SIZE = ['S' => 220, 'M' => 240, 'L' => 265, 'XL' => 290];

    public function __construct(protected SkuBuilderService $skus) {}

    public function run(): void
    {
        $products = Product::query()
            ->where('status', 'published')
            ->with('skus')
            ->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No published products found — run the catalog seeders first.');

            return;
        }

        $variables = $this->variables();
        $built = 0;

        foreach ($products as $index => $product) {
            // Base price in minor units, taken from the product's existing variant
            // so the SKU matrix keeps the price the catalog seeders chose.
            $base = (int) ($product->variants->first()?->prices->first()?->price->value ?? 29900);

            $rows = $this->rows($product, $base, $index);

            $this->skus->save($product, $variables, $rows);
            $built++;
        }

        $this->command?->info("Built SKU matrix for {$built} products (".count($this->skus->combinations($variables)).' SKUs each).');
    }

    /**
     * The two-axis variant definition: a colour swatch axis (display_type
     * `color`, so the storefront renders hex chips) and a plain text size axis.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function variables(): array
    {
        return [
            [
                'name' => ['en' => 'Color', 'vi' => 'Màu sắc'],
                'display_type' => 'color',
                'values' => collect(self::COLORS)->map(fn (array $c) => [
                    'name' => ['en' => $c['en'], 'vi' => $c['vi']],
                    'color' => $c['hex'],
                    'image' => null,
                ])->all(),
            ],
            [
                'name' => ['en' => 'Size', 'vi' => 'Kích cỡ'],
                'display_type' => 'text',
                'values' => collect(self::SIZES)->map(fn (array $s) => [
                    'name' => ['en' => $s['en'], 'vi' => $s['vi']],
                    'color' => null,
                    'image' => null,
                ])->all(),
            ],
        ];
    }

    /**
     * One row per Cartesian combination, in the same order combinations()
     * produces them (colour outer, size inner) — save() binds rows to
     * combinations by position.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rows(Product $product, int $basePrice, int $productIndex): array
    {
        $prefix = Str::upper(Str::substr(Str::slug($product->translateAttribute('name') ?: 'sku'), 0, 6));
        $rows = [];
        $position = 0;

        foreach (self::COLORS as $ci => $color) {
            foreach (self::SIZES as $si => $size) {
                // Larger sizes cost a little more, mirroring real fashion pricing.
                $price = $basePrice + ($si * 1000);

                // Put a strike-through origin_price on the first colour only, so
                // some rows show a sale badge and others don't.
                $originPrice = $ci === 0 ? (int) round($price * 1.25) : 0;

                // Vary stock so the demo shows in-stock, low-stock and sold-out
                // states; one XL row per product is deliberately zero.
                $quantity = match (true) {
                    $size['en'] === 'XL' && $ci === 2 => 0,
                    $size['en'] === 'S' => 4,
                    default => 12 + (($productIndex + $ci + $si) % 9),
                };

                $rows[] = [
                    'sku' => sprintf('%s-%s-%s', $prefix.($productIndex + 1), Str::upper(Str::substr($color['en'], 0, 3)), $size['en']),
                    'model' => $product->translateAttribute('name').' / '.$color['en'].' / '.$size['en'],
                    'price' => $price,
                    'origin_price' => $originPrice,
                    'cost_price' => (int) round($price * 0.55),
                    'quantity' => $quantity,
                    'weight' => self::WEIGHT_BY_SIZE[$size['en']] ?? 250,
                    'images' => [],
                    'is_default' => $position === 0,
                    // One disabled SKU per product so the storefront's
                    // `status = published` filter has something to exclude.
                    'status' => ($ci === 2 && $size['en'] === 'S') ? 'disabled' : 'published',
                ];

                $position++;
            }
        }

        return $rows;
    }
}
