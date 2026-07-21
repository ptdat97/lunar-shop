<?php

namespace Modules\Customer\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Sanctum\NewAccessToken;
use Modules\Customer\Http\Requests\IssueTokenRequest;
use Modules\Customer\Http\Requests\RegisterTokenRequest;
use Modules\Customer\Http\Resources\UserResource;
use Modules\Customer\Services\AuthService;
use Modules\Customer\Services\TokenIssuer;

/**
 * Token (Personal Access Token) auth for native app / headless clients that
 * can't use the SPA cookie session. Same credentials + UserResource as the
 * cookie flow ({@see AuthController}); the only difference is it issues a
 * Sanctum bearer token and does NO session work. The existing `auth:sanctum`
 * routes already accept this token, so nothing downstream changes.
 *
 * Token policy (TTL, abilities, revocation) lives in {@see TokenIssuer}.
 */
class TokenAuthController extends Controller
{
    public function __construct(
        protected AuthService $auth,
        protected TokenIssuer $tokens,
    ) {}

    /**
     * POST /api/v1/auth/token  { email, password, device_name? }
     * Returns { data: User, token, token_expires_at }.
     */
    public function issue(IssueTokenRequest $request): JsonResponse
    {
        $user = $this->auth->verifyCredentials($request->input('email'), $request->input('password'));

        return $this->tokenResponse($user, $this->tokens->issue($user, $request->deviceName()));
    }

    /**
     * POST /api/v1/auth/token/register  { name, email, password, device_name? }
     * Creates the account and returns { data: User, token } (201).
     */
    public function register(RegisterTokenRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        return $this->tokenResponse($user, $this->tokens->issue($user, $request->deviceName()), 201);
    }

    /**
     * POST /api/v1/auth/token/refresh — trade the current token for a fresh one
     * before it expires. Requires `auth:sanctum`.
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokens->refresh($user);

        if (! $token) {
            return response()->json(['message' => 'Only token clients can refresh.'], 400);
        }

        return $this->tokenResponse($user, $token);
    }

    /**
     * POST /api/v1/auth/token/revoke — revoke the token used for this request
     * (logout for a token client). Requires `auth:sanctum`.
     */
    public function revoke(Request $request): JsonResponse
    {
        $this->tokens->revokeCurrent($request->user());

        return response()->json(['data' => ['status' => 'token_revoked']]);
    }

    /**
     * The same shape as the cookie auth flow ({ data: User }), plus a top-level
     * `token` and the expiry the client should refresh before. The expiry is
     * read off the stored token rather than recomputed from config.
     */
    protected function tokenResponse(User $user, NewAccessToken $token, int $status = 200): JsonResponse
    {
        return UserResource::make($user)
            ->additional([
                'token' => $token->plainTextToken,
                'token_expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ])
            ->response()
            ->setStatusCode($status);
    }
}
