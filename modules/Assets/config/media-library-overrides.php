<?php

use Modules\Assets\Support\Library\LibraryFileRemover;
use Modules\Assets\Support\Library\LibraryPathGenerator;

/**
 * Module-local overrides for Spatie's `media-library` config — the two seams
 * that make a gallery image able to be a link to a library file
 * (Modules\Assets\Services\LibraryLinks). Applied by AssetsServiceProvider
 * over config/media-library.php, the same way overrides.php is over Lunar's.
 *
 * Both classes behave exactly like Spatie's defaults for every media row that
 * is not a link.
 */
return [
    // A link reads its original from the library file's directory; its
    // renditions stay in its own.
    'path_generator' => LibraryPathGenerator::class,

    // Deleting a link removes its renditions, never the library's original.
    'file_remover_class' => LibraryFileRemover::class,
];
