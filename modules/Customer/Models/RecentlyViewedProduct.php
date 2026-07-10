<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One product a signed-in shopper looked at, newest-first.
 *
 * @property int $user_id
 * @property int $product_id
 */
class RecentlyViewedProduct extends Model
{
    protected $table = 'recently_viewed_products';

    protected $guarded = [];

    protected $casts = [
        'viewed_at' => 'datetime',
        'sequence' => 'integer',
    ];
}
