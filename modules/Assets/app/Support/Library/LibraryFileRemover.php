<?php

namespace Modules\Assets\Support\Library;

use Illuminate\Support\Facades\Storage;
use Modules\Assets\Services\LibraryLinks;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;

/**
 * Deleting a link removes the link, never the library's original.
 *
 * Spatie deletes a media row's files when the row is deleted: its original,
 * its conversions and its responsive images. For a library link the
 * "original" is the library's file ({@see LibraryPathGenerator}), shared with
 * every other gallery that shows it — the default remover would take it from
 * all of them the moment one product dropped the photo. So for a link only
 * the link's own directory goes: its renditions.
 *
 * Plugged in through `media-library.file_remover_class`, Spatie's own seam.
 */
class LibraryFileRemover extends DefaultFileRemover
{
    public function removeAllFiles(Media $media): void
    {
        if (! LibraryLinks::isLink($media)) {
            parent::removeAllFiles($media);

            return;
        }

        foreach (array_unique(array_filter([$media->conversions_disk, $media->disk])) as $disk) {
            Storage::disk($disk)->deleteDirectory(LibraryPathGenerator::ownDirectory($media));
        }
    }
}
