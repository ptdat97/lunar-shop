<?php

namespace Modules\Cart\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class CartController extends Controller
{
    /**
     * Cart page shell — the island fetches /api/v1/cart.
     */
    public function __invoke(): View
    {
        return view('theme::pages.cart');
    }
}
