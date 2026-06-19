{{-- Product card. Included with ['product' => $product].
     This markup is the canonical card; Bước 2's enhance/_card.js will render the
     exact same structure from JSON so the SSR grid and JS-rendered grid match. --}}
@php
    $slug = $product->defaultUrl?->slug;
    $url = $slug ? route('storefront.product', $slug) : '#';
    $name = $product->translateAttribute('name');

    $thumb = $product->thumbnail;
    $image = null;
    if ($thumb) {
        try {
            $image = $thumb->hasGeneratedConversion('medium')
                ? $thumb->getUrl('medium')
                : $thumb->getUrl();
        } catch (\Throwable $e) {
            $image = null;
        }
    }
@endphp

<article class="product-card h-100 position-relative">
    <button class="btn btn-light btn-sm rounded-circle product-card__wishlist position-absolute top-0 end-0 m-2"
            data-wishlist-toggle data-product-id="{{ $product->id }}" aria-label="Add to wishlist" aria-pressed="false">
        <i class="bi bi-heart"></i>
    </button>
    <a href="{{ $url }}" class="d-block product-card__media rounded mb-2">
        @if($image)
            <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
        @else
            <span class="d-flex h-100 align-items-center justify-content-center text-muted small">
                {{ $name }}
            </span>
        @endif
    </a>
    <div class="product-card__body">
        @if($brand = $product->brand?->name)
            <div class="text-muted small text-uppercase">{{ $brand }}</div>
        @endif
        <h3 class="product-card__title mb-1">
            <a href="{{ $url }}" class="text-dark text-decoration-none">{{ $name }}</a>
        </h3>
        @include('theme::components.price', ['product' => $product])
    </div>
</article>
