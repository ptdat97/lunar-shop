<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Modules\Catalog\Services\ProductService;
use Modules\Customer\Models\RecentlyViewedProduct;

/**
 * "Recently viewed", server-side, for shoppers we can identify.
 *
 * The storefront keeps its localStorage list — it works for guests and needs no
 * round trip — but a signed-in shopper should see the same products after
 * switching to the app, which has no access to the browser's storage.
 */
class RecentlyViewedService
{
    /** Never keep more than this per user; older entries fall off. */
    public const MAX = 20;

    public function __construct(
        protected ProductService $products,
    ) {}

    /**
     * Record a view, moving the product to the top of the list.
     *
     * Only published products are recorded — an unpublished one would be stored
     * and then silently dropped on read (productsFor filters on status), which
     * is just a wasted row. Returns false when the product is not recordable so
     * callers can 404 without re-querying.
     */
    public function record(User $user, int $productId): bool
    {
        if (! Product::whereKey($productId)->where('status', 'published')->exists()) {
            return false;
        }

        DB::transaction(function () use ($user, $productId) {
            // `sequence` orders the list, not `viewed_at`: consecutive views
            // share a millisecond, so a timestamp cannot separate them. Read
            // the max under the row lock the transaction gives us, so two
            // devices viewing at once cannot claim the same slot.
            $next = (int) RecentlyViewedProduct::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->max('sequence') + 1;

            RecentlyViewedProduct::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $productId],
                ['viewed_at' => now(), 'sequence' => $next],
            );

            $this->trim($user);
        });

        return true;
    }

    /**
     * The user's list, newest first, hydrated as published products.
     *
     * @return Collection<int, Product>
     */
    public function productsFor(User $user, int $limit = 12): Collection
    {
        $ids = $this->productIdsFor($user, $limit);

        return $ids === [] ? collect() : $this->products->byIds($ids, $limit);
    }

    /**
     * @return array<int, int>
     */
    public function productIdsFor(User $user, int $limit = self::MAX): array
    {
        return RecentlyViewedProduct::query()
            ->where('user_id', $user->id)
            ->orderByDesc('sequence')
            ->limit($limit)
            ->pluck('product_id')
            ->all();
    }

    public function clear(User $user): int
    {
        return RecentlyViewedProduct::where('user_id', $user->id)->delete();
    }

    /**
     * Drop everything past the cap, so the table cannot grow without bound for
     * a shopper who browses a lot.
     */
    protected function trim(User $user): void
    {
        $keep = $this->productIdsFor($user, self::MAX);

        if (count($keep) < self::MAX) {
            return;
        }

        RecentlyViewedProduct::where('user_id', $user->id)
            ->whereNotIn('product_id', $keep)
            ->delete();
    }
}
