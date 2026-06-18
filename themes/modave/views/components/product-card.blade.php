@props(['product'])

@php
    $name = $product->translateAttribute('name');
    $slug = $product->defaultUrl?->slug;
    $url = $slug ? route('storefront.product', $slug) : '#';
    $variant = $product->variants->first();
    $price = $variant
        ? \Lunar\Facades\Pricing::for($variant)->get()->matched->price->formatted()
        : null;
    $img = $product->thumbnail && $product->thumbnail->hasGeneratedConversion('medium')
        ? $product->thumbnail->getUrl('medium')
        : ($product->thumbnail?->getUrl() ?? '/themes/modave/images/products/womens/women-19.jpg');
@endphp

<div class="card-product">
    <div class="card-product-wrapper">
        <a href="{{ $url }}" class="product-img">
            <img class="lazyload img-product" data-src="{{ $img }}" src="{{ $img }}" alt="{{ $name }}">
            <img class="lazyload img-hover" data-src="{{ $img }}" src="{{ $img }}" alt="{{ $name }}">
        </a>
        <div class="list-product-btn">
            {{-- Vanilla wishlist toggle (enhanced by enhance/wishlist.js) --}}
            <a href="javascript:void(0);" class="box-icon wishlist btn-icon-action" data-wishlist-toggle="{{ $product->id }}">
                <span class="icon icon-heart"></span>
                <span class="tooltip">Add to wishlist</span>
            </a>
            <a href="javascript:void(0);" class="box-icon quickview"
               onclick="window.dispatchEvent(new CustomEvent('quickview:open', {detail:{slug:'{{ $slug }}'}}))">
                <span class="icon icon-eye"></span><span class="tooltip">Quick View</span>
            </a>
        </div>
        <div class="list-btn-main">
            {{-- Vanilla add-to-cart (delegated by enhance/add-to-cart.js → /api/v1/cart).
                 Dispatches cart:updated so the Vue cart drawer/count refresh. --}}
            <button type="button" class="btn-main-product"
                    data-add-to-cart data-variant="{{ $variant?->id }}">Add To cart</button>
        </div>
    </div>
    <div class="card-product-info">
        <a href="{{ $url }}" class="title link">{{ $name }}</a>
        @if ($price)
            <span class="price">{{ $price }}</span>
        @endif
    </div>
</div>
