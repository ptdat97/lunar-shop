<?php

namespace Modules\Customer\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lunar\Core\Models\Product;
use Modules\Catalog\Services\ProductService;
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
            // Danh sách chung của thẻ sản phẩm. Bản viết tay ở đây thiếu
            // `defaultUrl` (một truy vấn mỗi thẻ, cho chính cái link của thẻ)
            // và thiếu `prices`, nên mỗi variant lại tự đi lấy giá:
            // 108 truy vấn cho 8 thẻ, giờ còn 10.
            ->with(ProductService::cardRelations())
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
