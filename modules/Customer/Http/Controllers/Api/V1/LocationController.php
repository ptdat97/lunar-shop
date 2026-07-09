<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Customer\Models\Province;
use Modules\Customer\Services\LocationService;

/**
 * Vietnam province/ward lookups for the address dropdowns. Public read-only;
 * lightweight payloads (code + name) served cached from LocationService.
 */
class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locations,
    ) {}

    /**
     * GET /api/v1/locations/provinces
     */
    public function provinces(): JsonResponse
    {
        return response()->json([
            'data' => $this->locations->provinces(),
        ]);
    }

    /**
     * GET /api/v1/locations/provinces/{province}/wards
     */
    public function wards(Province $province): JsonResponse
    {
        return response()->json([
            'data' => $this->locations->wards($province),
        ]);
    }
}
