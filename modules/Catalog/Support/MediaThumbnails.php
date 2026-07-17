<?php

namespace Modules\Catalog\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Back-fills the `thumbnail` relation on models that already have their full
 * `media` collection loaded.
 *
 * Lunar defines `thumbnail` as a separate MorphOne filtered to
 * custom_properties.primary === true, so eager-loading BOTH `thumbnail` and
 * `media` costs two media queries — the second one just re-selects the primary
 * item that's already inside `media`. Loading only `media` and setting the
 * thumbnail relation from it in PHP removes that redundant primary-filtered
 * query per result set (a real win when several product carousels render on one
 * page, e.g. the home page).
 */
class MediaThumbnails
{
    /**
     * Set each model's `thumbnail` relation to the primary item of its loaded
     * `media` collection. Models without `media` loaded are skipped (so accessing
     * `->thumbnail` there still lazy-loads as before — no behaviour change).
     *
     * @param  Collection<int, Model>  $models
     */
    public static function backfill(Collection $models): void
    {
        foreach ($models as $model) {
            if (! $model->relationLoaded('media')) {
                continue;
            }

            $primary = $model->media->first(
                fn ($m) => $m->getCustomProperty('primary') === true
            );

            $model->setRelation('thumbnail', $primary);
        }
    }
}
