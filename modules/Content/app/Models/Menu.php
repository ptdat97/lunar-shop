<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = ['handle', 'name'];

    /**
     * Top-level items (no parent), ordered.
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id')->orderBy('sort');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public static function findByHandle(string $handle): ?self
    {
        return static::where('handle', $handle)->first();
    }
}
