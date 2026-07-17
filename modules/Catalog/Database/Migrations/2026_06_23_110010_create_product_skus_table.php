<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

/**
 * VaniCommerce-style SKU table. A ProductSku IS a purchasable (implements
 * Lunar\Base\Purchasable) and a priceable, replacing ProductVariant as the
 * thing carts/orders point at. Every SKU carries its own commercial data:
 *
 *   - variants:     JSON array of integer indexes into products.variables
 *                   (the positional Cartesian combination this SKU represents).
 *                   Empty/null for a simple (single) product.
 *   - images:       JSON array of media ids / urls for this exact combination.
 *   - sku:          the STABLE identifier. Because saving a product's variants
 *                   is delete-and-recreate (ids are not stable across edits),
 *                   cart/order lines identify a purchasable by this string, not
 *                   by the surrogate id.
 *   - price/…:      admin-facing minor-unit cache. The authoritative prices
 *                   the Pricing engine reads live in `lunar_prices`
 *                   (priceable_type = product_sku); SkuBuilderService syncs
 *                   this column down to a base Price row.
 *   - status:       'published' | 'disabled'. 'disabled' hides the SKU on the
 *                   storefront AND is a real guard in CartService (§17.4).
 *
 * Prefixed `lunar_` for consistency with the rest of the schema. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable($this->prefix.'product_skus')) {
            return;
        }

        Schema::create($this->prefix.'product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained($this->prefix.'products')
                ->cascadeOnDelete();
            $table->foreignId('tax_class_id')
                ->nullable()
                ->constrained($this->prefix.'tax_classes')
                ->nullOnDelete();
            $table->json('variants')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->json('images')->nullable();
            $table->string('model')->default('');
            $table->string('sku')->index();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('origin_price')->default(0);
            $table->unsignedBigInteger('cost_price')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('weight', 8, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('published')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->prefix.'product_skus');
    }
};
