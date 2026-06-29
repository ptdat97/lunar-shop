{{-- $menus (MenuRenderer) injected by the Menu view composer (standards §7). --}}
@if($topbar = $theme->get('topbar'))
    <div class="bg-dark text-white small text-center py-2 px-3">
        {{ collect($topbar)->pluck('text')->first() }}
    </div>
@endif

<header class="site-header" data-header>
    <nav class="navbar navbar-expand-lg bg-white">
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
            <ul class="navbar-nav d-none d-lg-flex flex-row gap-3 mx-auto mb-0">
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
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="mobileMenuLabel">{{ __('storefront.nav.menu') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('storefront.common.cancel') }}"></button>
    </div>
    <div class="offcanvas-body">
        <div class="accordion accordion-flush" id="mobileMenuAccordion">
            {!! $menus->renderMobile('header') !!}
        </div>
        {{-- Language switcher (mobile) --}}
        <div class="border-top pt-3 mt-3">
            @include('theme::partials.language-switcher')
        </div>
    </div>
</div>
