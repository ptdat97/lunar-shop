<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;

/**
 * One successful referral, from registration to reward.
 *
 * @property int $referral_code_id
 * @property int $referrer_customer_id
 * @property ?int $referred_customer_id
 * @property ?int $referred_user_id
 * @property string $status
 * @property ?int $order_id
 * @property ?int $welcome_discount_id
 * @property ?int $reward_discount_id
 * @property ?string $fingerprint
 * @property ?string $voided_reason
 */
class ReferralClaim extends Model
{
    protected $table = 'referral_claims';

    /** A friend registered through the code; the welcome coupon is issued. */
    public const CLAIMED = 'claimed';

    /** Their first order is PAID — waiting out the return window. */
    public const AWAITING = 'awaiting';

    /** The window passed and the order was not given back: the referrer was paid. */
    public const REWARDED = 'rewarded';

    /** Self-referral, same device, returned order, or a staff decision. */
    public const VOIDED = 'voided';

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::CLAIMED => 'claimed',
            self::AWAITING => 'awaiting',
            self::REWARDED => 'rewarded',
            self::VOIDED => 'voided',
        ];
    }

    protected $fillable = [
        'referral_code_id',
        'referrer_customer_id',
        'referred_customer_id',
        'referred_user_id',
        'status',
        'order_id',
        'welcome_discount_id',
        'reward_discount_id',
        'fingerprint',
        'voided_reason',
        'claimed_at',
        'qualified_at',
        'rewarded_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'qualified_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    /** The customer who earns the reward. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    /** The invited customer, once they exist. */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    /** The order that qualifies the reward — the invited customer's first paid one. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** Still somewhere in the reward pipeline. */
    public function isSettled(): bool
    {
        return in_array($this->status, [self::REWARDED, self::VOIDED], true);
    }
}
