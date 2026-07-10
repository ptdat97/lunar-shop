<?php

namespace Modules\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Models\CustomerMeasurement;

/**
 * A customer's body-measurement profile: only the values they actually filled in.
 *
 * Delegates to the model's `values()` rather than re-listing the keys, so adding
 * a measurement cannot leave the API shape behind. A customer with no profile is
 * rendered by the controller as `[]`, not `null` — the address/size forms iterate
 * over it.
 *
 * @mixin CustomerMeasurement
 */
class MeasurementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->resource->values();
    }
}
