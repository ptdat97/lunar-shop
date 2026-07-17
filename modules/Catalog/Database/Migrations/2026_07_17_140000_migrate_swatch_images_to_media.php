<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lunar\Models\Product;
use Modules\Assets\Definitions\FashionMediaDefinitions;

/**
 * Move legacy variant swatch images (a raw path stored in the product's
 * `variables` blob, e.g. "variant-swatches/x.png") into the Spatie `swatch`
 * media collection and rewrite the blob to store the media id, so the storefront
 * serves the small `swatch` conversion instead of the heavy original.
 *
 * Idempotent: a value already holding an int id (or an absolute URL) is skipped;
 * a missing file becomes null. preservingOriginal() keeps the source file so a
 * re-run / rollback stays safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $collection = FashionMediaDefinitions::SWATCH_COLLECTION;

        Product::whereNotNull('variables')->cursor()->each(function (Product $product) use ($collection) {
            $variables = $product->variables ?? [];
            $changed = false;

            foreach ($variables as $ai => $axis) {
                if (($axis['display_type'] ?? null) !== 'image') {
                    continue;
                }

                foreach ($axis['values'] ?? [] as $vi => $value) {
                    $img = $value['image'] ?? null;

                    // Skip ids (already migrated), absolute URLs, blanks.
                    if (empty($img) || is_numeric($img) || Str::startsWith($img, ['http://', 'https://'])) {
                        continue;
                    }

                    $path = ltrim($img, '/');

                    if (Storage::disk('media')->exists($path)) {
                        $media = $product->addMedia(Storage::disk('media')->path($path))
                            ->preservingOriginal()
                            ->toMediaCollection($collection);
                        $variables[$ai]['values'][$vi]['image'] = $media->id;
                    } else {
                        $variables[$ai]['values'][$vi]['image'] = null;
                    }

                    $changed = true;
                }
            }

            if ($changed) {
                $product->variables = $variables;
                $product->save();
            }
        });
    }

    public function down(): void
    {
        // One-way data migration: the original paths are not recoverable from
        // ids. The source files remain (preservingOriginal), so no data is lost.
    }
};
