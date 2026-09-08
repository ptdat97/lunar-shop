<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;

/**
 * Seeds Size + Color options with values and assigns a couple to each existing
 * variant, so storefront filters (size/color) have real data. Idempotent.
 */
class DemoOptionsSeeder extends Seeder
{
    public function run(): void
    {
        $size = $this->option('Size', ['S', 'M', 'L', 'XL']);
        $color = $this->option('Color', ['Black', 'White', 'Beige', 'Gray'], type: ProductOptionType::Colour);

        $sizeValues = $size->values()->get()->values();
        $colorValues = $color->values()->get()->values();

        // Assign one size + one color to each variant, cycling through values.
        ProductVariant::query()->get()->each(function (ProductVariant $variant, int $i) use ($sizeValues, $colorValues) {
            $variant->values()->syncWithoutDetaching([
                $sizeValues[$i % $sizeValues->count()]->id,
                $colorValues[$i % $colorValues->count()]->id,
            ]);
        });
    }

    /**
     * Shared (so the variant builder surfaces them as global axes) with the
     * given display type — 'color' renders hex/image swatches on the
     * storefront, everything else is plain text.
     *
     * @param  array<int, string>  $values
     */
    protected function option(string $name, array $values, ProductOptionType $type = ProductOptionType::Text): ProductOption
    {
        $option = ProductOption::query()
            ->whereJsonContains('name->en', $name)
            ->first()
            ?? ProductOption::create([
                'name' => ['en' => $name],
                'label' => ['en' => $name],
                'handle' => strtolower($name),
                'shared' => true,
                'type' => $type->value,
            ]);

        if (! $option->shared || $option->type !== $type->value) {
            $option->shared = true;
            $option->type = $type->value;
            $option->save();
        }

        foreach ($values as $value) {
            $exists = $option->values()->whereJsonContains('name->en', $value)->exists();

            if (! $exists) {
                ProductOptionValue::create([
                    'product_option_id' => $option->id,
                    'name' => ['en' => $value],
                ]);
            }
        }

        return $option;
    }
}
