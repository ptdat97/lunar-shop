<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Customer\Http\Resources\UserResource;

/**
 * Token (Personal Access Token) auth for native app / headless clients that
 * can't use the SPA cookie session. Same credentials + UserResource as the
 * cookie flow ({@see AuthController}); the only difference is it issues a
 * Sanctum bearer token and does NO session work. The existing `auth:sanctum`
 * routes already accept this token, so nothing downstream changes.
 *
 * The SPA storefront keeps using the cookie endpoints — this is purely additive.
 */
class TokenAuthController extends Controller
{
    /**
     * POST /api/v1/auth/token  { email, password, device_name? }
     * Returns { data: User, token }.
     */
    public function issue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->respondWithToken($user, $data['device_name'] ?? null);
    }

    /**
     * POST /api/v1/auth/token/register  { name, email, password, device_name? }
     * Creates the account and returns { data: User, token } (201).
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        return $this->respondWithToken($user, $data['device_name'] ?? null, 201);
    }

    /**
     * POST /api/v1/auth/token/revoke — revoke the token used for this request
     * (logout for a token client). Requires `auth:sanctum`.
     */
    public function revoke(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        // Only delete a real PAT; a cookie-session "transient" token has no delete().
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['data' => ['status' => 'token_revoked']]);
    }

    /**
     * Issue a Sanctum token for the user and shape the response consistently
     * with the cookie auth flow ({ data: User } + a top-level `token`).
     */
    protected function respondWithToken(User $user, ?string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName ?: 'api')->plainTextToken;

        return UserResource::make($user)
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode($status);
    }
}
