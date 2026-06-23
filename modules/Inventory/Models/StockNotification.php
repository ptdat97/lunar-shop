<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\ProductVariant;

/**
 * A back-in-stock subscription: notify {email} when {variant} is restocked.
 *
 * @property int $id
 * @property int $product_variant_id
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $notified_at
 */
class StockNotification extends Model
{
    protected $fillable = [
        'product_variant_id',
        'email',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** Subscriptions still waiting to be notified. */
    public function scopePending($query)
    {
        return $query->whereNull('notified_at');
    }
}
