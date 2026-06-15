<?php

namespace Modules\Customer\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class WishlistController extends Controller
{
    /**
     * Wishlist page shell — the island fetches /api/v1/wishlist.
     */
    public function __invoke(): View
    {
        return view('theme::pages.wishlist');
    }
}
