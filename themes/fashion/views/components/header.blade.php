<nav class="site-header">
    <a href="{{ route('storefront.home') }}" class="logo">{{ config('app.name') }}</a>

    <div class="site-header__search">
        {{-- Vue island: autocomplete → /api/v1/search/suggest --}}
        <div data-vue="search-autocomplete"></div>
    </div>

    {{-- Vue island: cart drawer trigger → /api/v1/cart --}}
    <div data-vue="cart-drawer"></div>
</nav>
