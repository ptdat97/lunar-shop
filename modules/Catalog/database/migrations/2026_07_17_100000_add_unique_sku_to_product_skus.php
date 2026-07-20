<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lunar\Base\Migration;

/**
 * Make the `sku` code the durable, DB-enforced identifier it is meant to be.
 *
 * SkuBuilderService::assertUniqueSkuCodes() checks uniqueness in PHP, but that
 * check runs before the transaction and cannot stop two concurrent saves from
 * both persisting the same code — leaving true duplicates that break id-vs-code
 * resolution (cart/order/discount re-pointing, stock release by identifier).
 *
 * A PLAIN unique on `sku` (not `(sku, deleted_at)`): MySQL treats NULLs as
 * distinct in a composite unique, so `(sku, deleted_at NULL)` would NOT block a
 * second live row with the same code — defeating the purpose. The normal save
 * path force-deletes SKUs before recreating them (in one transaction), so no
 * soft-deleted row lingers to collide; a plain unique is safe and is exactly the
 * live-duplicate guard we want. Also serves as the `sku` lookup index, so the
 * old plain index is dropped first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = $this->prefix.'product_skus';

        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            // The create migration added `->index()` on sku with the default name.
            if ($this->hasIndex($table, $this->prefix.'product_skus_sku_index')) {
                $blueprint->dropIndex($this->prefix.'product_skus_sku_index');
            }
        });

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unique('sku', 'product_skus_sku_unique');
        });
    }

    public function down(): void
    {
        $table = $this->prefix.'product_skus';

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropUnique('product_skus_sku_unique');
            $blueprint->index('sku');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn ($i) => $i['name'] === $index);
    }
};
