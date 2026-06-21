<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lunar\Models\Product;
use Modules\Customer\Models\WishlistItem;

/**
 * Single source for wishlist reads/writes. Both the storefront page and the API
 * go through here, so the membership/product lookup isn't duplicated across
 * controllers (and the cross-module Product query lives in one place).
 */
class WishlistService
{
    /**
     * The product ids on a user's wishlist.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function productIdsFor(User $user)
    {
        return $this->itemsFor($user)->pluck('product_id');
    }

    /**
     * The wishlisted products, with the relations the product card needs.
     *
     * @return Collection<int, Product>
     */
    public function productsFor(User $user): Collection
    {
        return Product::query()
            ->whereIn('id', $this->productIdsFor($user))
            ->with(['variants', 'thumbnail', 'brand'])
            ->get();
    }

    /**
     * Toggle a product's membership. Returns the new state.
     *
     * @return array{in_wishlist: bool, count: int}
     */
    public function toggle(User $user, int $productId): array
    {
        $existing = WishlistItem::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            WishlistItem::create(['user_id' => $user->id, 'product_id' => $productId]);
            $inWishlist = true;
        }

        return [
            'in_wishlist' => $inWishlist,
            'count' => $this->itemsFor($user)->count(),
        ];
    }

    /**
     * Base query for a user's wishlist items.
     */
    protected function itemsFor(User $user)
    {
        return WishlistItem::where('user_id', $user->id);
    }
}
