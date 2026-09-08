<?php

namespace Modules\Catalog\Models;

use Modules\Core\Support\Concerns\SkipsEmptyTranslations;

/**
 * Registered over Lunar's own model via ModelManifest::replace() in
 * CatalogServiceProvider, purely to carry {@see SkipsEmptyTranslations}.
 *
 * `ProductOptionValue` keeps its name in a translatable JSON column, so it reads through
 * `translate()` — the method that returns an empty string when the current
 * locale's key exists but is blank.
 */
class ProductOptionValue extends \Lunar\Core\Models\ProductOptionValue
{
    use SkipsEmptyTranslations;
}
