<?php

use Illuminate\Database\Migrations\Migration;
use Lunar\Core\Enums\FieldTypeEnum;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\AttributeGroup;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;

/**
 * SEO fields for products (meta title/description) as first-class Lunar
 * attributes in their own "SEO" group — the product editor renders them (with
 * per-locale tabs) natively, and the theme layer reads them with
 * translateAttribute(). Idempotent.
 *
 * Rewritten for the Lunar 2.0 attribute schema, which reshaped this table in
 * three ways:
 *  - `attribute_groups.attributable_type` and `attributes.attribute_type` are
 *    gone. Which models an attribute applies to now lives in the
 *    `attribute_models` pivot (one row per morph name), and a group's `handle`
 *    is globally unique rather than unique per attributable type.
 *  - `name` on both tables is a plain string column, not a translatable JSON
 *    map. (Both names here were identical in en and vi, so nothing is lost.)
 *  - `description`, `section` and `default_value` no longer exist on attributes,
 *    and `type` holds a `FieldTypeEnum` value string rather than a class name.
 *
 * The upgrade path already converted the existing rows, so this only has to be
 * right for a database built from the v2 baseline — CI, and any fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        $group = AttributeGroup::firstOrCreate([
            'handle' => 'seo',
        ], [
            'name' => 'SEO',
            'position' => 99,
        ]);

        $attributes = [
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
        ];

        $position = 1;

        foreach ($attributes as $handle => $name) {
            $attribute = Attribute::firstOrCreate([
                'handle' => $handle,
            ], [
                'attribute_group_id' => $group->id,
                'position' => $position++,
                'name' => $name,
                // A FieldTypeEnum value, not a class name: 2.0 (spec 0019)
                // converted `attributes.type` from the FQCN to the enum string,
                // and FieldTypeManifest resolves by that string.
                'type' => FieldTypeEnum::TranslatedText->value,
                'required' => false,
                'configuration' => ['richtext' => false],
                'system' => false,
                'searchable' => false,
                'filterable' => false,
            ]);

            // What `attribute_type` used to say on the row itself.
            $attribute->models()->firstOrCreate([
                'model_type' => Product::morphName(),
            ]);

            ProductType::query()->each(
                fn (ProductType $type) => $type->attributeMapping()->syncWithoutDetaching([$attribute->id])
            );
        }
    }

    public function down(): void
    {
        $attributes = Attribute::whereIn('handle', ['meta_title', 'meta_description'])->get();

        foreach ($attributes as $attribute) {
            ProductType::query()->each(
                fn (ProductType $type) => $type->attributeMapping()->detach($attribute->id)
            );

            // The pivot rows go with it (cascadeOnDelete), and so does the
            // model's own deleting hook.
            $attribute->delete();
        }

        AttributeGroup::where('handle', 'seo')->delete();
    }
};
