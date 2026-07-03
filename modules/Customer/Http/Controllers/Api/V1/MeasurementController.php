<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Models\CustomerMeasurement;
use Modules\Customer\Services\CustomerResolver;
use Modules\Customer\Services\MeasurementService;

/**
 * The authenticated customer's saved body-measurement profile (Size
 * Intelligence v2). Used by the storefront to prefill "find my size" and to
 * save what the shopper enters.
 */
class MeasurementController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
        protected MeasurementService $measurements,
    ) {}

    /** GET /api/v1/customer/measurements — the saved profile (or empty). */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $customer = $user ? $this->customers->existingForUser($user) : null;

        return response()->json([
            'data' => $customer ? $this->measurements->for($customer) : [],
        ]);
    }

    /** PUT /api/v1/customer/measurements — create/update the profile. */
    public function update(Request $request): JsonResponse
    {
        $customer = $this->customers->forUser($request->user());

        $rules = [];
        foreach (CustomerMeasurement::KEYS as $key) {
            $rules[$key] = ['nullable', 'numeric', 'min:1', 'max:300'];
        }
        $data = $request->validate($rules);

        $profile = $this->measurements->save($customer, $data);

        return response()->json(['data' => $profile->values()]);
    }
}
