<?php

namespace Modules\Catalog\Models;

use Lunar\Models\ProductOption as BaseProductOption;

/**
 * Adds a per-option `display_type` to Lunar's ProductOption without forking
 * the vendor model (extend, don't fork).
 *
 * Registered over Lunar's own model via ModelManifest::replace() in
 * CatalogServiceProvider, so every `ProductOption::modelClass()` lookup —
 * relations, Filament resources, seeders — resolves to this class.
 *
 * The value is stored inside the existing `meta` JSON column, so adopting it
 * needs no migration and nothing breaks if the row predates the feature.
 */
class ProductOption extends BaseProductOption
{
    /**
     * How an option renders in the storefront picker and the admin variant
     * builder: plain text chips, a hex colour swatch (per-value colour picker
     * + optional swatch image), or an image swatch (per-value image only).
     * Configured per option on the Product Options admin page — nothing is
     * keyed to a hardcoded handle.
     */
    public const DISPLAY_TYPES = ['text', 'color', 'image'];

    /** Expose display_type on toArray() so Filament forms can fill it. */
    protected $appends = ['display_type'];

    /**
     * Display type, stored in meta so no schema change is needed. Unknown or
     * absent values fall back to 'text'.
     */
    public function getDisplayTypeAttribute(): string
    {
        $type = data_get($this->meta, 'display_type');

        return in_array($type, self::DISPLAY_TYPES, true) ? $type : 'text';
    }

    public function setDisplayTypeAttribute(?string $type): void
    {
        $meta = collect($this->meta ?? [])->toArray();
        $meta['display_type'] = in_array($type, self::DISPLAY_TYPES, true) ? $type : 'text';

        $this->meta = $meta;
    }
}
