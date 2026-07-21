<?php

namespace Modules\Customer\Services;

use Lunar\Models\Customer;
use Modules\Customer\Models\CustomerMeasurement;

/**
 * Reads/writes a customer's saved body-measurement profile (Size Intelligence
 * v2). One profile per customer; used to prefill "find my size".
 */
class MeasurementService
{
    /**
     * The customer's saved measurements as a { key: value } map (empty if none).
     *
     * @return array<string, float>
     */
    public function for(Customer $customer): array
    {
        return $customer->measurement?->values() ?? [];
    }

    /**
     * The customer's profile record, or null when they have never saved one.
     *
     * The API renders it through a Resource (§6), which needs the model rather
     * than the flattened map {@see self::for()} returns.
     */
    public function profileFor(Customer $customer): ?CustomerMeasurement
    {
        return $customer->measurement;
    }

    /**
     * Create/update the customer's profile from a measurement map. Only known
     * keys are stored; blank/null values clear that measurement.
     *
     * @param  array<string, mixed>  $measurements
     */
    public function save(Customer $customer, array $measurements): CustomerMeasurement
    {
        $clean = [];
        foreach (CustomerMeasurement::KEYS as $key) {
            $value = $measurements[$key] ?? null;
            $clean[$key] = ($value === null || $value === '') ? null : (float) $value;
        }

        return CustomerMeasurement::updateOrCreate(
            ['customer_id' => $customer->id],
            $clean,
        );
    }
}
