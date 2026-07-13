<?php

use Illuminate\Database\Migrations\Migration;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\Product;
use Lunar\Models\ProductType;

/**
 * SEO fields for products (meta title/description) as first-class Lunar
 * attributes in their own "SEO" group — the product editor renders them (with
 * per-locale tabs) natively via the Attributes form component, and the theme
 * layer reads them with translateAttribute(). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $group = AttributeGroup::firstOrCreate([
            'handle' => 'seo',
            'attributable_type' => Product::morphName(),
        ], [
            'name' => ['en' => 'SEO', 'vi' => 'SEO'],
            'position' => 99,
        ]);

        $attributes = [
            'meta_title' => ['en' => 'Meta title', 'vi' => 'Meta title'],
            'meta_description' => ['en' => 'Meta description', 'vi' => 'Meta description'],
        ];

        $position = 1;

        foreach ($attributes as $handle => $name) {
            $attribute = Attribute::firstOrCreate([
                'handle' => $handle,
                'attribute_type' => Product::morphName(),
            ], [
                'attribute_group_id' => $group->id,
                'position' => $position++,
                'name' => $name,
                'description' => ['en' => ''],
                'section' => 'main',
                'type' => TranslatedText::class,
                'required' => false,
                'default_value' => null,
                'configuration' => ['richtext' => false],
                'system' => false,
                'searchable' => false,
                'filterable' => false,
            ]);

            ProductType::query()->each(
                fn (ProductType $type) => $type->mappedAttributes()->syncWithoutDetaching([$attribute->id])
            );
        }
    }

    public function down(): void
    {
        $attributes = Attribute::whereIn('handle', ['meta_title', 'meta_description'])
            ->where('attribute_type', Product::morphName())
            ->get();

        foreach ($attributes as $attribute) {
            ProductType::query()->each(
                fn (ProductType $type) => $type->mappedAttributes()->detach($attribute->id)
            );
            $attribute->delete();
        }

        AttributeGroup::where('handle', 'seo')
            ->where('attributable_type', Product::morphName())
            ->delete();
    }
};
