<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Models\Customer;

/**
 * A customer's saved body-measurement profile (Size Intelligence v2). Columns
 * mirror the size-chart measurement vocabulary; used to prefill "find my size".
 */
class CustomerMeasurement extends Model
{
    protected $table = 'customer_measurements';

    protected $guarded = [];

    protected $casts = [
        'bust' => 'float',
        'waist' => 'float',
        'hip' => 'float',
        'shoulder' => 'float',
        'length' => 'float',
        'inseam' => 'float',
    ];

    /** Measurement keys (mirrors the size-chart vocabulary). */
    public const KEYS = ['bust', 'waist', 'hip', 'shoulder', 'length', 'inseam'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * The non-null measurements as a { key: value } map.
     *
     * @return array<string, float>
     */
    public function values(): array
    {
        return collect(self::KEYS)
            ->mapWithKeys(fn ($k) => [$k => $this->{$k}])
            ->filter(fn ($v) => $v !== null)
            ->all();
    }
}
