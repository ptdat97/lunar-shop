<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Customer\Http\Resources\LocationResource;
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
    public function provinces(): AnonymousResourceCollection
    {
        return LocationResource::collection($this->locations->provinces());
    }

    /**
     * GET /api/v1/locations/provinces/{province}/wards
     */
    public function wards(Province $province): AnonymousResourceCollection
    {
        return LocationResource::collection($this->locations->wards($province));
    }
}
