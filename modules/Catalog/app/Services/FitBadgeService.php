<?php

namespace Modules\Catalog\Services;

use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Product;
use Modules\Catalog\Http\Controllers\Api\V1\SizeController;
use Modules\Customer\Services\CustomerResolver;

/**
 * The "Your size: M" badge on product cards (docs/roadmap.md §14).
 *
 * One product's badge is answered by {@see FitHistoryService::badge()} — the
 * conservative, grid-safe rule (only a size the shopper actually bought and
 * kept). This service is the request-scoped seam that makes it cheap:
 *
 *  - the customer is resolved once per request (sanctum user → Lunar customer),
 *    so guests and sign-ins without a linked customer cost nothing;
 *  - the badge per product is memoised, and the underlying history/chart
 *    lookups inside FitHistoryService are memoised too, so a whole grid is flat
 *    no matter how many cards it renders.
 *
 * Bound `scoped` in CatalogServiceProvider (per request, Octane-safe) — the
 * memo must never outlive the request it was warmed for.
 */
class FitBadgeService
{
    /** @var array<int, string|null> product id => badge size (null = no badge) */
    protected array $badges = [];

    protected ?Customer $customer = null;

    protected bool $customerResolved = false;

    public function __construct(
        protected FitHistoryService $history,
        protected CustomerResolver $customers,
    ) {}

    /**
     * Badge size for one product card, or null. Safe to call per card from view
     * composers and API resources alike — repeat calls are O(1).
     */
    public function for(Product $product): ?string
    {
        $id = (int) $product->id;

        if (! array_key_exists($id, $this->badges)) {
            $customer = $this->customer();
            $this->badges[$id] = $customer ? $this->history->badge($customer, $product) : null;
        }

        return $this->badges[$id];
    }

    /**
     * The signed-in shopper's Lunar customer, resolved once per request.
     *
     * A guest — or an authenticated user with no linked customer, e.g. admin
     * staff browsing the storefront — has no badge. The sanctum guard covers
     * both the SPA cookie session and a bearer token, the same resolution
     * {@see SizeController} uses.
     */
    protected function customer(): ?Customer
    {
        if ($this->customerResolved) {
            return $this->customer;
        }

        $this->customerResolved = true;

        // sanctum covers both the SPA cookie session and a bearer token (the
        // same resolution SizeController uses); the default guard catches the
        // plain `web` pages where sanctum never runs. Either way, no user, no
        // badge.
        $user = request()?->user('sanctum') ?? request()?->user() ?? null;

        $this->customer = $user ? $this->customers->existingForUser($user) : null;

        return $this->customer;
    }
}
