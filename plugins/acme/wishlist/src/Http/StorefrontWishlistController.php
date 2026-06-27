<?php

namespace Acme\Wishlist\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Acme\Wishlist\WishlistService;

class StorefrontWishlistController extends Controller
{
    public function __construct(
        protected WishlistService $wishlist,
    ) {}

    /**
     * Wishlist page (SSR). Renders the user's wishlisted products server-side;
     * vanilla JS only enhances the remove/toggle interaction.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('theme::pages.wishlist', [
            'products' => $user ? $this->wishlist->productsFor($user) : collect(),
            'authed' => Auth::check(),
        ]);
    }
}
