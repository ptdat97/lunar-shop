<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Http\Resources\UserResource;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\OrderService;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
        protected OrderService $orders,
    ) {}

    /**
     * GET /api/v1/customer  — the current user's profile, or `null` for a
     * guest. Public on purpose: the storefront calls this on load to detect
     * login state, so a guest must get 200 + null rather than a 401 error.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user ? UserResource::make($user)->resolve($request) : null,
        ]);
    }

    /**
     * PATCH /api/v1/customer  — update name / email.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        // Keep the linked customer's name in sync.
        $customer = $this->customers->existingForUser($user);
        if ($customer) {
            $parts = preg_split('/\s+/', trim($data['name']), 2) ?: [];
            $customer->update(['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '']);
        }

        return UserResource::make($user->refresh())->response();
    }

    /**
     * PATCH /api/v1/customer/password  — change password.
     */
    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['data' => ['status' => 'password_updated']]);
    }

    /**
     * GET /api/v1/customer/orders — order history (clean Resource shape).
     */
    public function orders(Request $request): JsonResponse
    {
        $customer = $this->customers->existingForUser($request->user());

        if (! $customer) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => OrderResource::collection($this->orders->customerOrders($customer->id))->resolve(),
        ]);
    }
}
