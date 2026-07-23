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

    /**
     * Delete every item in this menu, deepest level first.
     *
     * `menu_items.parent_id` is a self-referencing FK with ON DELETE CASCADE.
     * MySQL evaluates such a cascade by expanding the referenced table once per
     * nesting level and refuses the statement past 30 expansions, so a plain
     * `$menu->items()->delete()` fails with:
     *
     *   SQLSTATE[HY000] 6575: Foreign key cascade delete/update exceeds max
     *   tables limit of 30
     *
     * The limit counts expansions of the cascade graph, not the tree's depth —
     * a three-level menu still trips it. Deleting leaves before their parents
     * means no row being removed still has children, so the engine never has to
     * expand the self-cascade at all.
     */
    public function deleteItems(): void
    {
        // Bottom-up: repeatedly remove items that are nobody's parent. Bounded
        // by the tree's depth, and the guard stops a cycle from looping forever.
        //
        // The parent ids are fetched into PHP rather than used as a subquery:
        // MySQL refuses to read the same table it is deleting from
        // (error 1093, "can't specify target table for update in FROM").
        for ($pass = 0; $pass < 50; $pass++) {
            $parentIds = MenuItem::query()
                ->whereNotNull('parent_id')
                ->distinct()
                ->pluck('parent_id')
                ->all();

            $deleted = $this->items()
                ->when($parentIds, fn ($q) => $q->whereNotIn('id', $parentIds))
                ->delete();

            if ($deleted === 0) {
                break;
            }
        }
    }
}
