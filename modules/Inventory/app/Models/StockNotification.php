<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\ProductSku;

/**
 * A back-in-stock subscription: notify {email} when {sku} is restocked.
 *
 * @property int $id
 * @property int $product_sku_id
 * @property string $email
 * @property Carbon|null $notified_at
 */
class StockNotification extends Model
{
    protected $fillable = [
        'product_sku_id',
        'email',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    /** Subscriptions still waiting to be notified. */
    public function scopePending($query)
    {
        return $query->whereNull('notified_at');
    }
}
