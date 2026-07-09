<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
     * @return array<string, string> type => label
     */
    public const TYPES = [
        'promotions-strip' => 'Promotions Strip',
        'hero-slider' => 'Hero Slider',
        'collection-grid' => 'Collection Grid',
        'product-tabs' => 'Product Tabs',
        'promotion-slider' => 'Promotion Slider',
        'lookbook' => 'Lookbook',
        'testimonial' => 'Testimonial',
        'iconbox' => 'Icon Box',
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
     * Cache key for a page's section CONFIG (rows: type/sort/settings), used by
     * SectionRenderer. Only the configuration is cached — the data each section
     * shows (products, prices, media) is fetched live by its provider.
     */
    public static function cacheKey(string $handle): string
    {
        return "content.sections.{$handle}";
    }

    /**
     * Any admin change to a section (create/edit/reorder/toggle/delete) busts
     * the rendered page's config cache — including the OLD page when a section
     * is moved between handles.
     */
    protected static function booted(): void
    {
        $bust = function (self $section): void {
            Cache::forget(static::cacheKey($section->page_handle));

            $original = $section->getOriginal('page_handle');
            if ($original && $original !== $section->page_handle) {
                Cache::forget(static::cacheKey($original));
            }
        };

        static::saved($bust);
        static::deleted($bust);
    }

    /**
     * Enabled sections for a page handle, ordered.
     *
     * NB: do NOT name this scope "forPage" — that collides with Laravel's
     * Builder::forPage() used internally by paginate(), which silently breaks
     * pagination (empty table in admin).
     */
    public function scopeForPageHandle(Builder $query, string $handle): Builder
    {
        return $query->where('page_handle', $handle)
            ->where('enabled', true)
            ->orderBy('sort');
    }
}
