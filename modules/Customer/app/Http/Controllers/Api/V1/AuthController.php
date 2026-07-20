<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Http\Resources\UserResource;
use Modules\Customer\Services\AuthService;

/**
 * Session/cookie auth for the storefront (Sanctum SPA). Wraps Laravel auth —
 * nothing reimplemented; account creation lives in AuthService (shared with
 * the token flow).
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $auth,
    ) {}

    /**
     * POST /api/v1/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = $this->auth->register($data);

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return UserResource::make($request->user())->response();
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['status' => 'logged_out']]);
    }
}
