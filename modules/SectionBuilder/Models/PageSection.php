<?php

namespace Modules\SectionBuilder\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A storefront page section. `type` maps to a Blade partial in the active
 * theme's views/sections directory; `settings` holds per-section content.
 */
class PageSection extends Model
{
    /**
     * Section types available to the admin. Each maps to a Blade partial in
     * the active theme (theme::sections.{type}).
     *
     * @return array<string, string>  type => label
     */
    public const TYPES = [
        'hero-slider' => 'Hero Slider',
        'category-grid' => 'Category Grid',
        'product-tabs' => 'Product Tabs',
        'promotion-slider' => 'Promotion Slider',
        'lookbook' => 'Lookbook',
        'testimonial' => 'Testimonial',
        'iconbox' => 'Icon Box',
        'instagram' => 'Instagram Gallery',
    ];

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

    /**
     * Enabled sections for a page handle, ordered.
     *
     * NB: do NOT name this scope "forPage" — that collides with Laravel's
     * Builder::forPage() used internally by paginate(), which silently breaks
     * pagination (empty table in admin).
     */
    public function scopeForPageHandle(\Illuminate\Database\Eloquent\Builder $query, string $handle): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('page_handle', $handle)
            ->where('enabled', true)
            ->orderBy('sort');
    }
}
