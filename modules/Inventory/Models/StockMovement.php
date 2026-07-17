<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Modules\Catalog\Models\ProductSku;
use Modules\Inventory\Enums\StockMovementType;

/**
 * One append-only entry in the stock ledger.
 *
 * @property int $id
 * @property int $product_sku_id
 * @property string $type
 * @property int $quantity signed delta
 * @property int $stock_before
 * @property int $stock_after
 * @property ?string $reason
 * @property ?int $order_id
 * @property ?array $meta
 * @property Carbon $created_at
 */
class StockMovement extends Model
{
    /** Append-only: rows are created once, never updated. */
    public $timestamps = false;

    protected $fillable = [
        'product_sku_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'reason',
        'order_id',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'product_sku_id');
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
