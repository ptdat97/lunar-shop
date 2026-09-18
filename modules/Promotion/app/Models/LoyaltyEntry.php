<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;

/**
 * Một bút toán điểm. Xem migration cho hình dạng của sổ cái và lý do.
 *
 * @property int $customer_id
 * @property ?int $lot_id
 * @property ?int $order_id
 * @property string $type
 * @property int $points
 */
class LoyaltyEntry extends Model
{
    /** Điểm thưởng từ một đơn đã thanh toán. Là một LÔ. */
    public const EARN = 'earn';

    /** Điểm tiêu vào một đơn. Trừ, ăn vào một lô. */
    public const SPEND = 'spend';

    /** Phần chưa tiêu của một lô đã quá hạn. Trừ. */
    public const EXPIRE = 'expire';

    /** Thu hồi điểm của một đơn đã trả/hoàn tiền. Trừ. */
    public const REVOKE = 'revoke';

    /** Trả lại điểm đã tiêu khi đơn bị huỷ/hoàn. Là một LÔ mới. */
    public const REFUND = 'refund';

    /** Staff cộng/trừ tay. Cộng thì là một LÔ. */
    public const ADJUST = 'adjust';

    protected $table = 'loyalty_entries';

    protected $fillable = [
        'customer_id', 'lot_id', 'order_id', 'type', 'points',
        'available_at', 'expires_at', 'reason',
    ];

    protected $casts = [
        'points' => 'int',
        'available_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Lô mà bút toán trừ này ăn vào. */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'lot_id');
    }

    /** Các bút toán trừ đã ăn vào lô này. */
    public function debits(): HasMany
    {
        return $this->hasMany(self::class, 'lot_id');
    }

    /**
     * Những bút toán đã tính vào số dư.
     *
     * Debit luôn `available_at` NULL nên luôn nằm trong; credit chỉ vào khi đã
     * qua ngày khả dụng. Đây là ĐỊNH NGHĨA của số dư — mọi chỗ đọc số dư phải đi
     * qua scope này, đừng viết lại điều kiện.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('available_at')->orWhere('available_at', '<=', now());
        });
    }

    /** Credit chưa tới ngày khả dụng — "điểm đang chờ" của khách. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('available_at', '>', now());
    }
}
