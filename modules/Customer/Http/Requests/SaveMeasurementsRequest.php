<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Customer\Models\CustomerMeasurement;

/**
 * PUT /api/v1/customer/measurements — save the body-measurement profile.
 *
 * Every measurement is optional: a shopper may know their bust but not their
 * inseam, and clearing one is done by sending it null.
 */
class SaveMeasurementsRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        // Derived from the model's key list so a new measurement is validated the
        // moment it exists, rather than silently ignored.
        return collect(CustomerMeasurement::KEYS)
            ->mapWithKeys(fn (string $key) => [
                $key => ['nullable', 'numeric', 'min:1', 'max:300'],
            ])
            ->all();
    }
}
