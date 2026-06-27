<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews plugin — its own table. Run by the plugin's install() via
 * `php artisan plugin:install acme/reviews`, not by the app's global migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $products = config('lunar.database.table_prefix', 'lunar_').'products';

        Schema::create('product_reviews', function (Blueprint $table) use ($products): void {
            $table->id();
            $table->foreignId('product_id')->constrained($products)->cascadeOnDelete();
            $table->string('author');
            $table->unsignedTinyInteger('rating');   // 1..5
            $table->text('body')->nullable();
            $table->boolean('approved')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'approved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
