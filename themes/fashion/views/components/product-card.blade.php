{{-- Product card. Included with ['product' => $product].
     $image (Media) + $sale (Promotion) are injected by view composers — no
     service resolution in the view (standards §7). This markup is the canonical
     card; enhance/_card.js renders the same structure from JSON so the SSR grid
     and JS-rendered grid match. --}}
@php
    $slug = $product->defaultUrl?->slug;
    $url = $slug ? route('storefront.product', $slug) : '#';
    $name = $product->translateAttribute('name');
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
    <a href="{{ $url }}" class="d-block product-card__media rounded mb-2 {{ ($hoverImage ?? false) ? 'has-hover' : '' }}">
        @if($picture ?? false)
            @include('theme::components.picture', [
                'picture' => $picture,
                'alt' => $name,
                'class' => 'product-card__img',
                'sizes' => '(min-width: 992px) 25vw, (min-width: 576px) 33vw, 50vw',
            ])
        @elseif($image)
            <img src="{{ $image }}" alt="{{ $name }}" class="product-card__img" loading="lazy">
        @else
            <span class="d-flex h-100 align-items-center justify-content-center text-muted small">
                {{ $name }}
            </span>
        @endif

        {{-- Secondary image revealed on hover (e.g. back/detail view). Lazy +
             aria-hidden: purely decorative, the primary image carries the alt. --}}
        @if($hoverImage ?? false)
            <img src="{{ $hoverImage }}" alt="" class="product-card__img product-card__img--hover"
                 loading="lazy" aria-hidden="true">
        @endif
    </a>
    <div class="product-card__body">
        @if($brand = $product->brand?->name)
            <div class="product-card__brand">{{ $brand }}</div>
        @endif
        <h3 class="product-card__title mb-1">
            <a href="{{ $url }}" class="text-dark text-decoration-none">{{ $name }}</a>
        </h3>
        <div class="product-card__price-wrap mb-1">
            @include('theme::components.price', ['product' => $product])
        </div>
    </div>
</article>
