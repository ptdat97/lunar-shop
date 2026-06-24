{{-- Product card. Included with ['product' => $product].
     This markup is the canonical card; Bước 2's enhance/_card.js will render the
     exact same structure from JSON so the SSR grid and JS-rendered grid match. --}}
@php
    $slug = $product->defaultUrl?->slug;
    $url = $slug ? route('storefront.product', $slug) : '#';
    $name = $product->translateAttribute('name');

    // Resolves the `medium` URL, generating the conversion on demand if missing.
    $image = app(\Modules\Media\Services\MediaUrl::class)->conversion($product->thumbnail, 'medium');

    // Best automatic promotion for this product (badge + optional price break).
    $sale = app(\Modules\Promotion\Services\PromotionService::class)->saleFor($product);
@endphp

<article class="product-card h-100 position-relative">
    @if($sale)
        <span class="product-card__badge badge position-absolute top-0 start-0 m-2 {{ ($sale['is_flash_sale'] ?? false) ? 'bg-danger' : 'bg-dark' }}"
              @if($sale['ends_at'] ?? false) data-promo-deadline="{{ $sale['ends_at'] }}" @endif>
            @if($sale['is_flash_sale'] ?? false)<i class="bi bi-lightning-charge-fill me-1"></i>@endif{{ $sale['label'] }}
        </span>
    @endif
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
