<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Notification\Http\Requests\RegisterDeviceRequest;
use Modules\Notification\Http\Requests\RevokeDeviceRequest;
use Modules\Notification\Http\Resources\DeviceResource;
use Modules\Notification\Services\DeviceRegistry;

/**
 * Push targets for the mobile app. Validate → delegate → respond (§3).
 */
class DeviceController extends Controller
{
    public function __construct(
        protected DeviceRegistry $devices,
    ) {}

    /**
     * POST /api/v1/devices  { token, platform, device_name? }
     */
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->devices->register(
            $request->user(),
            $request->string('token')->toString(),
            $request->string('platform')->toString(),
            $request->input('device_name'),
        );

        return DeviceResource::make($device)
            ->response()
            ->setStatusCode($device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * DELETE /api/v1/devices — stop pushing to this device (sign-out).
     *
     * Answers `{data:{deleted}}` rather than 204: the app distinguishes "we
     * removed it" from "it was never ours", and once the row is gone there is no
     * model left for a JsonResource to wrap.
     */
    public function destroy(RevokeDeviceRequest $request): JsonResponse
    {
        $deleted = $this->devices->revoke(
            $request->user(),
            $request->string('token')->toString(),
        );

        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}
