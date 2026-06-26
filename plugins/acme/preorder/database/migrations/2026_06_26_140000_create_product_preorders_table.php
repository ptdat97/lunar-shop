<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-order plugin — its own table. Marks which products may be pre-ordered
 * (bought while out of stock) and the expected availability date. Run by the
 * plugin's install(), not the app's global migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $products = config('lunar.database.table_prefix', 'lunar_').'products';

        Schema::create('product_preorders', function (Blueprint $table) use ($products): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained($products)->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->date('expected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_preorders');
    }
};
