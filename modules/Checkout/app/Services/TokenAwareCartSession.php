<?php

namespace Modules\Checkout\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lunar\Managers\CartSessionManager;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;

/**
 * Cart identity for stateless clients (mobile app, POS), layered on Lunar's own
 * CartSessionManager — inherited, not reimplemented (Principle #1).
 *
 * Lunar keys the current cart off the HTTP session. A Bearer-token client has no
 * session, so `fetchOrCreate()` found no key, and `AuthManager::user()` resolved
 * through the default `web` guard (session-backed) and returned null — meaning
 * even the built-in "fall back to the user's active cart" branch never fired.
 * Result: every API call minted a fresh cart.
 *
 * This subclass changes nothing for the web storefront (a request with a session
 * takes the parent path verbatim). For a token request it resolves the cart from,
 * in order:
 *
 *   1. the `X-Cart-Token` header — an opaque handle we mint per cart, so a guest
 *      on the app keeps their basket across requests and app restarts;
 *   2. the token-authenticated user's active cart.
 *
 * Newly created carts get a `public_token` so the client has something to send
 * back. The token is returned in the cart payload (see CartResource).
 */
class TokenAwareCartSession extends CartSessionManager
{
    /** Opaque handle a client sends to claim a cart it already owns. */
    public const HEADER = 'X-Cart-Token';

    /** Identifies a headless client that has no cart handle yet (first call). */
    public const CLIENT_HEADER = 'X-Client';

    /**
     * Is this a headless request — one that identifies its cart by header
     * rather than by session cookie?
     *
     * Three signals, any of which is enough. Shared with the CSRF middleware
     * (`VerifyCsrfTokenUnlessStateless`) so the two can never disagree about
     * what "stateless" means:
     *
     *  - `Authorization: Bearer …` — a signed-in app / POS terminal;
     *  - `X-Cart-Token: …` — a guest reclaiming its basket;
     *  - `X-Client: app` — a guest's *first* call, before it has a handle to
     *    send. Without this the server could not tell an app apart from the
     *    storefront's own fetch, and would hand the guest a session cart it can
     *    never address again.
     *
     * The storefront sends none of them.
     */
    public static function isStatelessRequest(?Request $request): bool
    {
        if (! $request) {
            return false;
        }

        return $request->bearerToken() !== null
            || filled($request->header(self::HEADER))
            || filled($request->header(self::CLIENT_HEADER));
    }

    protected function isStateless(): bool
    {
        return self::isStatelessRequest($this->request());
    }

    protected function request(): ?Request
    {
        return app()->bound('request') ? app('request') : null;
    }

    /**
     * The user behind a Bearer token. `AuthManager::user()` uses the default
     * (`web`, session) guard, which cannot see a token, so ask sanctum directly.
     */
    protected function tokenUser()
    {
        return $this->request()?->user('sanctum');
    }

    /**
     * Resolve the cart. Session-backed requests fall through to Lunar untouched.
     */
    protected function fetchOrCreate(bool $create = false, bool $estimateShipping = false, bool $calculate = true): ?Cart
    {
        if (! $this->isStateless()) {
            return parent::fetchOrCreate($create, $estimateShipping, $calculate);
        }

        $cart = $this->resolveStatelessCart();

        if (! $cart) {
            return $create ? $this->cart = $this->createNewCart() : null;
        }

        // A cart that already produced an order must not be reused (mirrors the
        // parent's guard), otherwise a second checkout would append to it.
        if ($cart->hasCompletedOrders() && ! $this->allowsMultipleOrdersPerCart()) {
            return $this->cart = $this->createNewCart();
        }

        $this->cart = $cart;

        if ($calculate) {
            $this->cart->calculate();
        }

        if ($estimateShipping) {
            $this->estimateShipping();
        }

        return $this->cart;
    }

    /**
     * Cart token first (a guest's basket), then the authenticated user's active
     * cart (so a signed-in app that lost its token still finds its basket).
     */
    protected function resolveStatelessCart(): ?Cart
    {
        $user = $this->tokenUser();
        $token = $this->request()?->header(self::HEADER);

        if ($token) {
            $cart = Cart::with(config('lunar.cart.eager_load', []))
                ->where('public_token', $token)
                ->first();

            // A cart owned by someone must not be reachable by its handle alone:
            // the handle travels in a header and is only as secret as the device.
            // Guest carts (user_id null) are claimable — that is what the handle
            // is for — and a user may of course present their own cart's handle.
            if ($cart && $this->mayUse($cart, $user)) {
                return $cart;
            }
        }

        if ($user) {
            $id = $user->carts()->active()->latest('id')->first()?->id;

            if ($id) {
                return Cart::with(config('lunar.cart.eager_load', []))->find($id);
            }
        }

        return null;
    }

    /** A cart is usable when it is unowned, or owned by this very user. */
    protected function mayUse(Cart $cart, mixed $user): bool
    {
        return $cart->user_id === null || ($user && $cart->user_id === $user->id);
    }

    /**
     * Stateless carts carry a `public_token` and are linked to the token user
     * when there is one. The parent's `createNewCart()` reads the wrong guard,
     * so it would leave `user_id` null for a signed-in app.
     */
    protected function createNewCart(): CartContract
    {
        if (! $this->isStateless()) {
            return parent::createNewCart();
        }

        $user = $this->tokenUser();

        $cart = Cart::create([
            'currency_id' => $this->getCurrency()->id,
            'channel_id' => $this->getChannel()->id,
            'user_id' => $user?->id,
            'customer_id' => $user?->latestCustomer()?->id,
            'public_token' => (string) Str::uuid(),
        ]);

        return $this->cart = $cart;
    }

    /**
     * Remember the cart. Writing the session key is pointless (and pollutes the
     * storefront session) for a stateless request.
     */
    public function use(CartContract $cart): CartContract
    {
        if (! $this->isStateless()) {
            return parent::use($cart);
        }

        return $this->cart = $cart;
    }

    /**
     * Drop the cart. Stateless requests have no session keys to clear.
     */
    public function forget(?bool $delete = null): void
    {
        if (! $this->isStateless()) {
            parent::forget($delete);

            return;
        }

        $delete = is_null($delete) ? config('lunar.cart_session.delete_on_forget', true) : $delete;

        if ($delete && $this->cart?->exists) {
            Cart::destroy($this->cart->id);
        }

        $this->cart = null;
    }
}
