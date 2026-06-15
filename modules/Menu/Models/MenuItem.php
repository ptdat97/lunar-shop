<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Collection as LunarCollection;

/**
 * A node in a menu tree. `type` decides how it renders (link/dropdown/mega/...).
 * URL resolves from `url`, else from a linked Collection slug.
 */
class MenuItem extends Model
{
    public const TYPES = [
        'link' => 'Link',
        'dropdown' => 'Dropdown',
        'mega' => 'Mega menu',
        'mega-column' => 'Mega column',
        'footer-column' => 'Footer column',
        'banner' => 'Banner',
    ];

    protected $fillable = [
        'menu_id', 'parent_id', 'type', 'label', 'url',
        'collection_id', 'target', 'image', 'badge', 'sort',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(LunarCollection::class, 'collection_id');
    }

    /**
     * Resolve the href: explicit url wins, else the linked collection slug.
     */
    public function resolveUrl(): string
    {
        if (filled($this->url)) {
            return $this->url;
        }

        if ($this->collection_id && ($slug = $this->collection?->defaultUrl?->slug)) {
            return "/collections/{$slug}";
        }

        return '#';
    }
}
