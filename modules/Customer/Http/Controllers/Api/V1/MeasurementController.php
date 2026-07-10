<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Http\Requests\SaveMeasurementsRequest;
use Modules\Customer\Http\Resources\MeasurementResource;
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
        $profile = $customer ? $this->measurements->profileFor($customer) : null;

        // `[]`, not `null`: the storefront iterates over this map. A Resource
        // wrapping null would publish `"data": null` instead.
        if (! $profile) {
            return response()->json(['data' => []]);
        }

        return MeasurementResource::make($profile)->response();
    }

    /** PUT /api/v1/customer/measurements — create/update the profile. */
    public function update(SaveMeasurementsRequest $request): JsonResponse
    {
        $customer = $this->customers->forUser($request->user());
        $profile = $this->measurements->save($customer, $request->validated());

        // Always 200: PUT is an upsert of the caller's single profile, and a
        // Resource would answer 201 the first time (`wasRecentlyCreated`),
        // changing a status the storefront already depends on.
        return MeasurementResource::make($profile)->response()->setStatusCode(200);
    }
}
