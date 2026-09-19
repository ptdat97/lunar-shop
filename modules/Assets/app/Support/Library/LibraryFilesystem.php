<?php

namespace Modules\Assets\Support\Library;

use Modules\Assets\Services\LibraryLinks;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Spatie's Filesystem, minus the two operations that would move the
 * library's original through a link.
 *
 * Spatie renames a row's files when its `file_name` changes, and moves them
 * when its path changes. For a link both "files" include the library's
 * original ({@see LibraryPathGenerator}) — renaming it for one gallery would
 * break the library and every other gallery showing it. A link's file name
 * only ever changes because the library file behind it was replaced, and
 * LibraryLinks::resync() then discards the link's renditions outright, so
 * there is nothing of the link's own to rename either.
 */
class LibraryFilesystem extends Filesystem
{
    public function syncFileNames(Media $media): void
    {
        if (LibraryLinks::isLink($media)) {
            return;
        }

        parent::syncFileNames($media);
    }

    public function syncMediaPath(Media $media): void
    {
        if (LibraryLinks::isLink($media)) {
            return;
        }

        parent::syncMediaPath($media);
    }
}
