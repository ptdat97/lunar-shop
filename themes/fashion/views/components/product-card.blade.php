@props(['product'])

@php
    $variant = $product->variants->first();
    $price = $variant ? \Lunar\Facades\Pricing::for($variant)->get()->matched->price->formatted() : null;
    $slug = $product->defaultUrl?->slug;
@endphp

<article class="product-card">
    <a href="{{ $slug ? route('storefront.product', $slug) : '#' }}" class="product-card__link">
        <div class="product-card__media">
            <x-theme::responsive-image
                :media="$product->thumbnail"
                :alt="$product->translateAttribute('name')"
                conversion="medium"
            />
        </div>
        <h3 class="product-card__name">{{ $product->translateAttribute('name') }}</h3>
        @if ($price)
            <p class="product-card__price">{{ $price }}</p>
        @endif
    </a>
</article>
