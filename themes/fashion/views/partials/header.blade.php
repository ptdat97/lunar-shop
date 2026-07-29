{{-- $menus (MenuRenderer) injected by the Menu view composer (standards §7). --}}
@if($topbar = $theme->get('topbar'))
    <div class="site-topbar">
        {{ collect($topbar)->pluck('text')->first() }}
    </div>
@endif

<header class="site-header" data-header>
    <nav class="navbar navbar-expand-lg bg-white w-100" >
        <div class="container">
            {{-- Mobile: hamburger toggles the off-canvas menu --}}
            <button class="navbar-toggler border-0 px-0 me-2" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Menu">
                <i class="bi bi-list fs-3"></i>
            </button>

            <a class="navbar-brand fw-bold text-uppercase" href="{{ route('storefront.home') }}">
                @if($logo = $theme->image('general.logo'))
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" height="32"
                         onerror="this.replaceWith(document.createTextNode('{{ config('app.name') }}'))">
                @else
                    {{ config('app.name') }}
                @endif
            </a>

            {{-- Desktop menu (real links → crawlable, no-JS friendly) --}}
            {{-- Horizontal gap handled by .nav-item padding in CSS --}}
            <ul class="navbar-nav d-none d-lg-flex flex-row mx-auto mb-0">
                {!! $menus->render('header') !!}
            </ul>

            <div class="d-flex align-items-center gap-3">
                @include('theme::partials.language-switcher')
                {{-- Search: a real link with no JS; the enhancer upgrades it to open
                     the search panel (autocomplete) instead of navigating. --}}
                <a href="{{ route('storefront.search') }}" class="text-dark fs-5" aria-label="{{ __('storefront.nav.search') }}"
                   data-search-toggle aria-expanded="false" aria-controls="searchPanel">
                    <i class="bi bi-search"></i>
                </a>
                <a href="{{ route('storefront.wishlist') }}" class="text-dark fs-5 position-relative" aria-label="{{ __('storefront.nav.wishlist') }}">
                    <i class="bi bi-heart"></i>
                    <span class="badge rounded-pill bg-dark position-absolute top-0 start-100 translate-middle"
                          data-wishlist-count hidden>0</span>
                </a>
                <a href="{{ route('storefront.account') }}" class="text-dark fs-5" aria-label="{{ __('storefront.nav.account') }}">
                    <i class="bi bi-person"></i>
                </a>
                {{-- Opens the mini-cart drawer; falls back to the cart page with no JS. --}}
                <a href="{{ route('storefront.cart') }}" class="text-dark fs-5 position-relative" aria-label="{{ __('storefront.nav.cart') }}"
                   data-bs-toggle="offcanvas" data-bs-target="#shoppingCart" data-cart-toggle>
                    <i class="bi bi-bag"></i>
                    <span class="badge rounded-pill bg-dark position-absolute top-0 start-100 translate-middle"
                          data-cart-count hidden>0</span>
                </a>
            </div>
        </div>
    </nav>
    @include('theme::partials.search-panel')
</header>

{{-- Mobile off-canvas menu --}}
<div class="offcanvas offcanvas-start mobile-menu" tabindex="-1" id="mobileMenu"
     aria-labelledby="mobileMenuLabel" data-mobile-menu>
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="mobileMenuLabel">{{ __('storefront.nav.menu') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('storefront.common.cancel') }}"></button>
    </div>

    <div class="offcanvas-body">
        {{-- Search first: on mobile it is the fastest path to a product. Real
             link to /search, so it works with the drawer open and no JS. --}}
        <a href="{{ route('storefront.search') }}" class="mobile-menu__search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span>{{ __('storefront.nav.search') }}</span>
        </a>

        <nav class="mobile-menu__nav" id="mobileMenuAccordion" aria-label="{{ __('storefront.nav.menu') }}">
            {!! $menus->renderMobile('header') !!}
        </nav>
    </div>

    {{-- Pinned footer: account actions stay reachable without scrolling past
         the whole category tree. --}}
    <div class="mobile-menu__footer">
        <div class="mobile-menu__actions">
            <a href="{{ route('storefront.account') }}" class="mobile-menu__action">
                <i class="bi bi-person" aria-hidden="true"></i>
                <span>{{ __('storefront.nav.account') }}</span>
            </a>
            <a href="{{ route('storefront.wishlist') }}" class="mobile-menu__action">
                <i class="bi bi-heart" aria-hidden="true"></i>
                <span>{{ __('storefront.nav.wishlist') }}</span>
                <span class="mobile-menu__badge" data-wishlist-count hidden>0</span>
            </a>
        </div>
        @include('theme::partials.language-switcher')
    </div>
</div>
