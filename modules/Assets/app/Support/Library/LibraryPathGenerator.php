<?php

namespace Modules\Assets\Support\Library;

use Modules\Assets\Services\LibraryLinks;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

/**
 * Where a media item's files live — with library links in mind.
 *
 * A gallery image (a product's, a collection's, a brand's, a swatch's) is a
 * real Spatie media row on that record, so everything Lunar reads — the
 * `thumbnail` relation, ordering, the primary flag, the variant pivot — keeps
 * working untouched. When the row is a LINK to a library file
 * ({@see LibraryLinks}), only its ORIGINAL is borrowed:
 *
 *   getPath()                     → the library file's directory (the one original)
 *   getPathForConversions()       → the link's own directory
 *   getPathForResponsiveImages()  → the link's own directory
 *
 * Renditions stay per record on purpose. Brands and product types use Lunar's
 * StandardDefinitions (a white-padded 300×300 `small`), products and the
 * library use FashionMediaDefinitions (a crop sized from settings) — the same
 * conversion names with different pixels. Sharing the conversions directory
 * would let one overwrite the other. Renditions are a cache derived from the
 * original; the original is the thing that must exist once.
 *
 * Everything else — URLs, the on-demand conversion route, FileManipulator —
 * asks the path generator, so none of it needs to know what a link is.
 */
class LibraryPathGenerator extends DefaultPathGenerator
{
    public function getPath(Media $media): string
    {
        $source = $media->getCustomProperty(LibraryLinks::SOURCE);

        return $source ? static::base((string) $source).'/' : parent::getPath($media);
    }

    /**
     * The directory holding only this row's own files. For a link that is its
     * renditions and nothing else — safe to delete whole.
     */
    public static function ownDirectory(Media $media): string
    {
        return static::base((string) $media->getKey());
    }

    protected static function base(string $key): string
    {
        $prefix = config('media-library.prefix', '');

        return $prefix !== '' ? $prefix.'/'.$key : $key;
    }
}
