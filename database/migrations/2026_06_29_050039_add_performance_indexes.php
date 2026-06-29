<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for common query patterns.
     * These indexes accelerate the most frequent queries identified during
     * profiling: product lookups, URL resolution, price filtering, facet
     * computation, and section rendering.
     */
    public function up(): void
    {
        // lunar_products: status + id for listing queries (already has status index)
        // Add composite index for brand filtering + status
        Schema::table('lunar_products', function (Blueprint $table) {
            $table->index(['brand_id', 'status'], 'lunar_products_brand_status_index');
        });

        // lunar_urls: composite index for polymorphic lookups with slug
        // The existing (element_type, element_id) index is good, but we also
        // need (slug, element_type) for findBySlug patterns
        Schema::table('lunar_urls', function (Blueprint $table) {
            $table->index(['slug', 'element_type', 'default'], 'lunar_urls_slug_type_default_index');
        });

        // lunar_prices: composite for price range filtering (priceable_type + price)
        // The existing (priceable_type, priceable_id) is good, but price range
        // queries need (priceable_type, price, priceable_id)
        Schema::table('lunar_prices', function (Blueprint $table) {
            $table->index(['priceable_type', 'price', 'currency_id'], 'lunar_prices_type_price_currency_index');
        });

        // lunar_product_variants: composite for variant lookups by product
        // Already has product_id index, but add (product_id, id) for joins
        $tableName = 'lunar_product_variants';
        if (!Schema::hasIndex($tableName, 'lunar_product_variants_product_id_id_index')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(['product_id', 'id'], 'lunar_product_variants_product_id_id_index');
            });
        }

        // lunar_collection_product: composite for collection→product lookups
        Schema::table('lunar_collection_product', function (Blueprint $table) {
            $table->index(['product_id', 'collection_id'], 'lunar_coll_prod_product_collection_index');
        });

        // lunar_discountables: composite for discount→product lookups
        Schema::table('lunar_discountables', function (Blueprint $table) {
            $table->index(['discount_id', 'discountable_type', 'discountable_id'], 'lunar_discountables_discount_type_id_index');
        });

        // lunar_product_option_value_product_variant: composite for variant→value lookups
        Schema::table('lunar_product_option_value_product_variant', function (Blueprint $table) {
            $table->index(['variant_id', 'value_id'], 'lunar_pov_pv_variant_value_index');
        });

        // lunar_product_option_values: add name index for JSON name lookups
        // (MySQL can't index JSON directly, but we add a generated column approach
        //  is not needed here since the data volume is small for options)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lunar_products', function (Blueprint $table) {
            $table->dropIndex('lunar_products_brand_status_index');
        });
        Schema::table('lunar_urls', function (Blueprint $table) {
            $table->dropIndex('lunar_urls_slug_type_default_index');
        });
        Schema::table('lunar_prices', function (Blueprint $table) {
            $table->dropIndex('lunar_prices_type_price_currency_index');
        });
        Schema::table('lunar_product_variants', function (Blueprint $table) {
            $table->dropIndex('lunar_product_variants_product_id_id_index');
        });
        Schema::table('lunar_collection_product', function (Blueprint $table) {
            $table->dropIndex('lunar_coll_prod_product_collection_index');
        });
        Schema::table('lunar_discountables', function (Blueprint $table) {
            $table->dropIndex('lunar_discountables_discount_type_id_index');
        });
        Schema::table('lunar_product_option_value_product_variant', function (Blueprint $table) {
            $table->dropIndex('lunar_pov_pv_variant_value_index');
        });
    }
};