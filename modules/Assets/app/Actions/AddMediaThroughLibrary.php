<?php

namespace Modules\Assets\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Lunar\Core\Actions\Media\AddMedia;
use Lunar\Core\Contracts\Actions\Media\AddsMedia;
use Modules\Assets\Services\LibraryLinks;
use Modules\Assets\Services\MediaLibraryService;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Every image uploaded into a Lunar gallery lands in the library first.
 *
 * Lunar funnels every panel upload — product, collection, brand and product
 * type galleries, option-value swatches — through the `AddsMedia` action, and
 * documents rebinding that contract as the way to change what an upload does.
 * This binding stores the file in the library (or finds the identical file
 * already there) and puts a link to it in the gallery ({@see LibraryLinks}),
 * so the library stays the one place an image file exists.
 *
 * Anything else goes to Lunar's own action unchanged: a record outside
 * LibraryLinks::MODELS, or a format the library does not take (SVG, AVIF).
 */
class AddMediaThroughLibrary implements AddsMedia
{
    public function __construct(
        protected AddMedia $native,
        protected MediaLibraryService $library,
        protected LibraryLinks $links,
    ) {}

    public function execute(HasMedia $model, UploadedFile $file, ?string $collection = null, array $customProperties = []): Media
    {
        $folder = $model instanceof Model ? $this->links->folderFor($model) : null;

        if ($folder === null || ! $this->library->accepts($file)) {
            return $this->native->execute($model, $file, $collection, $customProperties);
        }

        $asset = $this->library->storeOrReuse($file, $folder);

        return $this->links->link($asset, $model, $collection, $customProperties);
    }
}
