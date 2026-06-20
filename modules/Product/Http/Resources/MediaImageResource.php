<?php

namespace Modules\Product\Http\Resources;

use Modules\Media\Services\MediaSettings;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Serializes a Spatie Media model into the shape the storefront gallery needs:
 * the `large` URL for the visible thumbnail and the `zoom` URL + pixel size for
 * the PhotoSwipe lightbox. Conversions fall back to the original when not yet
 * generated (getUrl() throws otherwise — mirrors product.blade.php's @php block).
 *
 * Shared by ProductResource (product media) and ProductVariantResource (variant
 * images) so both feed the Vue gallery island an identical image shape.
 */
class MediaImageResource
{
    /**
     * @return array<int, array{id:int, large:?string, zoom:?string, width:int, height:int, primary:bool}>
     */
    public static function collection(iterable $media): array
    {
        $zoomSize = app(MediaSettings::class)->sizes()['zoom'];

        $images = [];
        foreach ($media as $item) {
            if ($one = self::one($item, $zoomSize)) {
                $images[] = $one;
            }
        }

        return $images;
    }

    /**
     * @param  array{width:int, height:int}|null  $zoomSize
     * @return array{id:int, large:?string, zoom:?string, width:int, height:int, primary:bool}|null
     */
    public static function one(?Media $media, ?array $zoomSize = null): ?array
    {
        if (! $media) {
            return null;
        }

        $zoomSize ??= app(MediaSettings::class)->sizes()['zoom'];
        $urls = app(\Modules\Media\Services\MediaUrl::class);

        // Generated on demand when their files are missing.
        $large = $urls->conversion($media, 'large');
        $zoom = $urls->conversion($media, 'zoom') ?? $large;

        if (! $large) {
            return null;
        }

        return [
            'id' => $media->id,
            'large' => $large,
            'zoom' => $zoom,
            'width' => (int) $zoomSize['width'],
            'height' => (int) $zoomSize['height'],
            // pivot->primary is only present for variant images (belongsToMany);
            // product media has no pivot, so it defaults to false.
            'primary' => (bool) ($media->pivot->primary ?? false),
        ];
    }
}
