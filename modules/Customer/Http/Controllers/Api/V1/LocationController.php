<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Customer\Models\Province;

/**
 * Vietnam province/ward lookups for the address dropdowns. Public read-only;
 * lightweight payloads (code + name) for dependent selects.
 */
class LocationController extends Controller
{
    /**
     * GET /api/v1/locations/provinces
     */
    public function provinces(): JsonResponse
    {
        return response()->json([
            'data' => Province::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    /**
     * GET /api/v1/locations/provinces/{province}/wards
     */
    public function wards(Province $province): JsonResponse
    {
        return response()->json([
            'data' => $province->wards()->get(['id', 'code', 'name']),
        ]);
    }
}
