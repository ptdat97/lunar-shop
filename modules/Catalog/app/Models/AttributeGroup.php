<?php

namespace Modules\Catalog\Models;

use Lunar\Models\AttributeGroup as BaseAttributeGroup;
use Modules\Core\Support\Concerns\SkipsEmptyTranslations;

/**
 * Registered over Lunar's own model via ModelManifest::replace() in
 * CatalogServiceProvider, purely to carry {@see SkipsEmptyTranslations}.
 *
 * `AttributeGroup` keeps its name in a translatable JSON column, so it reads through
 * `translate()` — the method that returns an empty string when the current
 * locale's key exists but is blank.
 */
class AttributeGroup extends BaseAttributeGroup
{
    use SkipsEmptyTranslations;
}
