<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Models\Customer;
use Lunar\Models\Order;

/**
 * A customer's return/RMA request against a paid order. Line-level (which items
 * + quantities) with a reason; staff approve/reject in the admin and, once
 * refunded, the gateway refund amount is recorded here.
 */
class ReturnRequest extends Model
{
    protected $table = 'return_requests';

    protected $guarded = [];

    protected $casts = [
        'refund_amount' => 'integer',
        'resolved_at' => 'datetime',
    ];

    /** Lifecycle statuses. */
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const REFUNDED = 'refunded';
    public const COMPLETED = 'completed';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReturnRequestLine::class, 'return_request_id');
    }

    /** Whether this request is still open (not decided/closed). */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::REQUESTED, self::APPROVED], true);
    }
}
