{{-- Mini-cart drawer (Bootstrap offcanvas). Personalised + session-scoped, so
     it fetches /api/v1/cart on open (allowed exception to SSR-first — not SEO
     content). enhance/cart.js renders into [data-cart-body]. --}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="shoppingCart" aria-labelledby="shoppingCartLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="shoppingCartLabel">Your cart</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        {{-- Scroll region: shipping + line items + recommendations all scroll
             together; the footer below stays pinned. --}}
        <div class="flex-grow-1 overflow-auto" data-cart-scroll>
            {{-- Free-shipping progress --}}
            <div class="px-3 pt-3 small text-muted" data-cart-shipping hidden></div>

            {{-- Line items (rendered by JS) --}}
            <div class="px-3" data-cart-body>
                <div class="text-center text-muted py-5" data-cart-loading>Loading…</div>
            </div>

            {{-- Empty state --}}
            <div class="text-center text-muted py-5 px-3" data-cart-empty hidden>
                <p class="mb-3">Your cart is empty.</p>
                <a href="{{ route('storefront.search') }}" class="btn btn-outline-dark btn-sm">Continue shopping</a>
            </div>

            {{-- "You may also like" — loaded from /api/v1/cart/recommendations
                 when the drawer opens (session-scoped, not SEO content). Hidden
                 until it has items. Same ProductResource shape → _card.js. --}}
            <div class="px-3 pb-3" data-cart-recommendations hidden>
                <h6 class="text-uppercase small text-muted mt-3 mb-2">You may also like</h6>
                <div class="row g-2" data-cart-recommendations-grid></div>
            </div>
        </div>

        {{-- Footer: totals + actions (pinned below the scroll region) --}}
        <div class="border-top p-3" data-cart-footer hidden>
            <div class="d-flex justify-content-between mb-2">
                <span>Subtotal</span>
                <strong data-cart-subtotal></strong>
            </div>
            {{-- Discount savings — shown by enhance/cart.js when discount_total > 0
                 (flash sale / quantity / combo / membership all surface here). --}}
            <div class="d-flex justify-content-between mb-2 text-success" data-cart-savings-row hidden>
                <span><i class="bi bi-tags-fill"></i> You saved</span>
                <strong data-cart-savings></strong>
            </div>
            <a href="{{ route('storefront.cart') }}" class="btn btn-outline-dark w-100 mb-2">View cart</a>
            <a href="{{ route('storefront.checkout') }}" class="btn btn-dark w-100">Checkout</a>
        </div>
    </div>
</div>
