<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Notification\Models\DeviceToken;

/**
 * Push targets for the mobile app.
 */
class DeviceController extends Controller
{
    /**
     * POST /api/v1/devices  { token, platform, device_name? }
     *
     * Idempotent: the app re-registers on every launch, and the platform may
     * hand the same token to a different account after a sign-out, so the token
     * is claimed by whoever registers it last.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $device = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'data' => ['id' => $device->id, 'platform' => $device->platform],
        ], $device->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * DELETE /api/v1/devices — stop pushing to this device (sign-out).
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        // Scoped to the caller: a token they do not own must not be revocable.
        $deleted = DeviceToken::where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['data' => ['deleted' => (bool) $deleted]]);
    }
}
