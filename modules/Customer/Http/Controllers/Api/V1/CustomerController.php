<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CustomerController extends Controller
{
    /**
     * GET /api/v1/customer  — the authenticated user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * GET /api/v1/customer/orders — order history (Lunar orders).
     */
    public function orders(Request $request): JsonResponse
    {
        $customers = $request->user()->customers ?? collect();

        $orders = \Lunar\Models\Order::query()
            ->whereIn('customer_id', $customers->pluck('id'))
            ->latest()
            ->limit(50)
            ->get(['id', 'reference', 'status', 'total', 'placed_at']);

        return response()->json(['data' => $orders]);
    }
}
