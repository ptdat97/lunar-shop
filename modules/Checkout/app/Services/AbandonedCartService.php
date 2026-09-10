<?php

namespace Modules\Checkout\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Core\Models\Cart;
use Modules\Core\Support\Settings;

/**
 * Finds carts worth a reminder, and decides who to send it to.
 *
 * The recovery email is the highest-value thing missing from this shop's
 * conversion path, and almost all of the machinery for it already existed:
 * carts persist (TokenAwareCartSession), the queue and scheduler run, and
 * `orders:expire-abandoned` already sweeps for stale work. What was missing was
 * only the nudge.
 *
 * The judgement lives here rather than in the command, because "which cart
 * deserves an email" is a shop rule and the command is a scheduler entry point.
 */
class AbandonedCartService
{
    /** Wait this long after the last cart change before nudging. */
    public const DEFAULT_DELAY_MINUTES = 60;

    /** Below this, the shopper is very likely still shopping. */
    public const MIN_DELAY_MINUTES = 15;

    /** Past this, the reminder reads as a stranger emailing about old news. */
    public const MAX_DELAY_MINUTES = 4320; // 3 ngày

    /**
     * Stop considering a cart entirely once it is this old. Without a floor the
     * sweep would keep scanning carts from months ago forever, and a shopper who
     * gets a "you left something behind" about a cart from last month is being
     * told the shop was not paying attention.
     */
    public const STALE_AFTER_DAYS = 7;

    public function __construct(protected Settings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('checkout.abandoned_cart_enabled', false);
    }

    public function delayMinutes(): int
    {
        $minutes = (int) $this->settings->get('checkout.abandoned_cart_minutes', self::DEFAULT_DELAY_MINUTES);

        return max(self::MIN_DELAY_MINUTES, min(self::MAX_DELAY_MINUTES, $minutes));
    }

    /**
     * Carts that have been sitting long enough to be worth a nudge.
     *
     * @return Collection<int, Cart>
     */
    public function dueForReminder(?int $minutes = null, int $limit = 100): Collection
    {
        $minutes ??= $this->delayMinutes();

        return $this->baseQuery()
            ->where('lunar_carts.updated_at', '<', now()->subMinutes($minutes))
            ->where('lunar_carts.updated_at', '>', now()->subDays(self::STALE_AFTER_DAYS))
            ->orderBy('lunar_carts.updated_at')
            ->limit($limit)
            ->get()
            // The recipient rule needs relations, so it cannot be a SQL filter.
            // Loading a bounded page first keeps that cheap.
            ->filter(fn (Cart $cart) => $this->recipient($cart) !== null)
            ->values();
    }

    /**
     * Who to email about this cart.
     *
     * Same shape as OrderMailer::recipient() and for the same reason: the
     * address a shopper typed at checkout is the one they expect to hear on,
     * and the account address is the fallback when they never got that far.
     * A cart with neither simply is not reachable — a guest who abandoned
     * before the address step has given the shop no way to reach them, and
     * guessing is not an option.
     */
    public function recipient(Cart $cart): ?string
    {
        $cart->loadMissing(['shippingAddress', 'billingAddress', 'user']);

        $email = $cart->shippingAddress?->contact_email
            ?: $cart->billingAddress?->contact_email
            ?: $cart->user?->email;

        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * The carts a sweep would consider, before the time window is applied.
     * Kept separate so tests and the dry run can describe the population.
     */
    protected function baseQuery(): Builder
    {
        return Cart::query()
            // Never reminded. One nudge per cart is deliberate: a second email
            // about the same cart is the point where a reminder becomes spam,
            // and a shop this size cannot afford the sender reputation.
            ->whereNull('abandoned_reminded_at')
            // Not already bought.
            ->whereNull('completed_at')
            ->whereNull('order_id')
            // A cart that was merged into another is not the shopper's cart.
            ->whereNull('merged_id')
            // Something in it.
            ->whereHas('lines')
            ->with(['lines.purchasable', 'currency', 'shippingAddress', 'billingAddress', 'user']);
    }

    /**
     * Give the cart a recovery handle if it has none.
     *
     * Lunar only mints `public_token` for stateless (API) carts — a session
     * cart from the web storefront has none, which is most carts a shop will
     * ever want to recover. Minted here rather than at cart creation because
     * this is the only place that needs it, and a token nobody uses is one more
     * thing that can leak.
     *
     * `withoutTimestamps` matters: bumping `updated_at` here would reset the
     * cart's own idea of when it was abandoned, and on a re-run the sweep would
     * see a fresh cart and skip it forever.
     */
    public function ensureRecoveryToken(Cart $cart): string
    {
        if (filled($cart->public_token)) {
            return (string) $cart->public_token;
        }

        $token = (string) Str::uuid();

        Cart::withoutTimestamps(fn () => $cart->forceFill(['public_token' => $token])->saveQuietly());

        return $token;
    }

    /**
     * Mark a cart as nudged. Recorded even when the mail fails to send, so a
     * broken mail transport cannot turn one reminder into a retry loop that
     * lands ten copies once it recovers.
     */
    public function markReminded(Cart $cart): void
    {
        // Same reason as ensureRecoveryToken: touching `updated_at` would make
        // the cart look freshly active to every later sweep.
        Cart::withoutTimestamps(fn () => $cart->forceFill(['abandoned_reminded_at' => now()])->saveQuietly());
    }
}
