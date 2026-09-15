<?php

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Core\Models\Customer;

/**
 * A customer's invite code — the thing they share.
 *
 * @property int $customer_id
 * @property string $code
 * @property ?string $fingerprint
 */
class ReferralCode extends Model
{
    protected $table = 'referral_codes';

    protected $fillable = ['customer_id', 'code', 'fingerprint'];

    /** The customer who owns (and is rewarded for) this code. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** Everyone who has registered through it. */
    public function claims(): HasMany
    {
        return $this->hasMany(ReferralClaim::class);
    }
}
