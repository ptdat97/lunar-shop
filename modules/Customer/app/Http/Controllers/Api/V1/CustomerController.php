<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Customer\Http\Resources\UserResource;
use Modules\Customer\Services\AuthService;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Http\Resources\CustomerOrderPage;
use Modules\Order\Services\OrderService;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerResolver $customers,
        protected OrderService $orders,
        protected AuthService $auth,
    ) {}

    /**
     * GET /api/v1/customer  — the current user's profile, or `null` for a
     * guest. Public on purpose: the storefront calls this on load to detect
     * login state, so a guest must get 200 + null rather than a 401 error.
     *
     * Resolved through the **sanctum** guard, not the default one. This route
     * sits in the `web` group (it has to: the SPA flow is cookie-based), and the
     * default guard there is session-backed — it cannot see a bearer token. A
     * token client therefore got `200 + null`, i.e. "you are a guest", while
     * holding a perfectly valid token, and no amount of correct client code
     * could fix it. The `sanctum` guard resolves BOTH a cookie session and a
     * bearer token, and still returns null for a real guest.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');

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
        $this->customers->syncName($user);

        return UserResource::make($user->refresh())->response();
    }

    /**
     * PATCH /api/v1/customer/password  — change password.
     */
    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->auth->changePassword($request->user(), $data['current_password'], $data['password']);

        return response()->json(['data' => ['status' => 'password_updated']]);
    }

    /**
     * GET /api/v1/customer/orders — order history (clean Resource shape).
     */
    public function orders(Request $request): JsonResponse
    {
        // The very same payload as `GET /api/v1/orders` — one shape, one place.
        return CustomerOrderPage::for(
            $request,
            $this->customers->existingForUser($request->user()),
            $this->orders,
        );
    }
}
