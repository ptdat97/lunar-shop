{{--
    Thumb-reachable bottom navigation, phones only (roadmap §15).

    This is not an addition to the mobile header — it REPLACES its action icons.
    The header used to carry six tappable things on a phone (hamburger, language,
    search, wishlist, account, cart) squeezed into one bar; those four now live
    here, at the bottom, where a thumb actually reaches. The header keeps the
    hamburger and the logo.

    Every item is a real link, so it works with no JS. The two badges reuse the
    same `data-cart-count` / `data-wishlist-count` hooks the header used — both
    enhancers already write to *every* match, so the counts stay live for free.
--}}
<nav class="bottom-nav d-lg-none" aria-label="{{ __('storefront.nav.menu') }}" data-bottom-nav>
    @php
        // `route()` names, not URLs: a request to /search?q=x still counts as the
        // search tab, and locale-prefixed URLs keep matching.
        $items = [
            ['route' => 'storefront.home', 'icon' => 'house', 'label' => __('storefront.nav.home')],
            ['route' => 'storefront.search', 'icon' => 'search', 'label' => __('storefront.nav.search'), 'search' => true],
            ['route' => 'storefront.wishlist', 'icon' => 'heart', 'label' => __('storefront.nav.wishlist'), 'count' => 'wishlist'],
            ['route' => 'storefront.cart', 'icon' => 'bag', 'label' => __('storefront.nav.cart'), 'count' => 'cart'],
            ['route' => 'storefront.account', 'icon' => 'person', 'label' => __('storefront.nav.account')],
        ];
    @endphp

    @foreach($items as $item)
        @php $active = request()->routeIs($item['route']); @endphp
        <a href="{{ route($item['route']) }}"
           class="bottom-nav__item @if($active) is-active @endif"
           @if($active) aria-current="page" @endif
           {{-- Search opens the panel in place; still a real link without JS. --}}
           @if($item['search'] ?? false) data-search-toggle aria-expanded="false" aria-controls="searchPanel" @endif>
            <span class="bottom-nav__icon">
                <i class="bi bi-{{ $item['icon'] }}" aria-hidden="true"></i>
                @if($item['count'] ?? false)
                    <span class="bottom-nav__badge" data-{{ $item['count'] }}-count hidden>0</span>
                @endif
            </span>
            <span class="bottom-nav__label">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
