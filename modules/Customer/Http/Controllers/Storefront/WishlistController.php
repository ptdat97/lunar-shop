<?php

namespace Modules\Customer\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Lunar\Models\Product;
use Modules\Customer\Models\WishlistItem;

class WishlistController extends Controller
{
    /**
     * Wishlist page (SSR). Renders the user's wishlisted products server-side;
     * vanilla JS only enhances the remove/toggle interaction.
     */
    public function __invoke(Request $request): View
    {
        $products = collect();

        if (Auth::check()) {
            $productIds = WishlistItem::where('user_id', $request->user()->id)->pluck('product_id');

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->with(['variants', 'thumbnail', 'brand'])
                ->get();
        }

        return view('theme::pages.wishlist', [
            'products' => $products,
            'authed' => Auth::check(),
        ]);
    }
}
