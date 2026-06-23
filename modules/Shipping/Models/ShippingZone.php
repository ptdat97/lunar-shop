<?php

namespace Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A shipping zone: a flat rate (with optional free-shipping threshold) for a
 * country, optionally narrowed to specific states/provinces.
 *
 * @property int $id
 * @property string $name
 * @property string $country_code
 * @property array<int, string>|null $states
 * @property int $rate
 * @property int $free_threshold
 * @property bool $enabled
 * @property int $priority
 */
class ShippingZone extends Model
{
    protected $fillable = [
        'name',
        'country_code',
        'states',
        'rate',
        'free_threshold',
        'enabled',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'states' => 'array',
            'rate' => 'integer',
            'free_threshold' => 'integer',
            'enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Whether this zone covers the given country + state. A zone with no states
     * covers the whole country.
     */
    public function covers(?string $countryCode, ?string $state): bool
    {
        if ($this->country_code !== $countryCode) {
            return false;
        }

        if (empty($this->states)) {
            return true;
        }

        return $state !== null && in_array($state, $this->states, true);
    }

    /**
     * Specificity: state-scoped zones (1) beat country-wide zones (0) when both
     * match. Ties broken by priority.
     */
    public function specificity(): int
    {
        return empty($this->states) ? 0 : 1;
    }

    /**
     * The effective rate for a cart sub-total: free when the threshold is met.
     */
    public function rateFor(int $subTotal): int
    {
        if ($this->free_threshold > 0 && $subTotal >= $this->free_threshold) {
            return 0;
        }

        return $this->rate;
    }
}
