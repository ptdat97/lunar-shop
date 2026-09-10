<?php

namespace Modules\Checkout\Http\Controllers\Storefront;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Cart;
use Modules\Checkout\Services\AbandonedCartService;

/**
 * The link in a "you left something behind" email.
 *
 * Restores the cart into the shopper's session and drops them on the cart page.
 * Keyed on `public_token`, the opaque handle Lunar already mints per cart and
 * that TokenAwareCartSession uses to let a guest reclaim a basket — not on the
 * id, which anyone could increment to open a stranger's cart.
 *
 * ⚠️ Anyone holding the link holds the cart, including the delivery address the
 * shopper typed at checkout. That is the same trade-off every cart-recovery
 * link makes, and it is bounded three ways: the token is unguessable, the link
 * stops working once the cart is bought, and it stops working once the cart is
 * older than the sweep's own staleness window. A forwarded email does not stay
 * live indefinitely.
 */
class CartRecoveryController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $cart = Cart::query()
            ->where('public_token', $token)
            // Already bought: the link has done its job and must not resurrect
            // a basket the shopper has since paid for.
            ->whereNull('completed_at')
            ->whereNull('order_id')
            ->whereNull('merged_id')
            // Same horizon the reminder sweep uses. A link from two months ago
            // opening a live cart is a surprise, not a convenience.
            ->where('updated_at', '>', now()->subDays(AbandonedCartService::STALE_AFTER_DAYS))
            ->first();

        if (! $cart) {
            // Not a 404: the shopper did nothing wrong, and a dead end reads as
            // the shop being broken. Send them to the cart they do have.
            return redirect()
                ->route('storefront.cart')
                ->with('status', __('storefront.cart.recovery_expired'));
        }

        CartSession::use($cart);

        return redirect()->route('storefront.cart');
    }
}
