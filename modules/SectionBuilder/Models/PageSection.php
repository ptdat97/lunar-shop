<?php

namespace Modules\SectionBuilder\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A storefront page section. `type` maps to a Blade partial in the active
 * theme's views/sections directory; `settings` holds per-section content.
 */
class PageSection extends Model
{
    protected $fillable = [
        'page_handle',
        'type',
        'sort',
        'enabled',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function scopeForPage($query, string $handle)
    {
        return $query->where('page_handle', $handle)
            ->where('enabled', true)
            ->orderBy('sort');
    }
}
