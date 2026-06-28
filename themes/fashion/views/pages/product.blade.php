@extends('theme::layouts.app')
@section('body_class', 'page-product')

@php
  // Presentation data is injected by view composers (standards §7):
  //   Media   → $zoomSize, $ogImage, $galleryImages
  //   Pricing → $displayPrice, $lowestPriceAmount, $currencyCode
  //   Promotion → $sale
  $name = $product->translateAttribute('name');
  $description = $product->translateAttribute('description');
  // Hydration payload for the variant enhancer — same ProductResource shape as
  // GET /api/v1/products/{slug} (SSR-first §8). resolve() serialises a Resource,
  // it isn't a business-service call.
$state = new \Modules\Product\Http\Resources\ProductResource(
    $product->loadMissing(['variants.values.option', 'variants.images', 'media']),
)->resolve();

$firstVariant = $product->variants->first();
$priceAmount = $lowestPriceAmount; // alias for the JSON-LD block below
$currency = $currencyCode;
$inStock = $product->variants->sum('stock') > 0;
@endphp

@section('title', $name . ' — ' . config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $description), 155))
@section('og_type', 'product')
@if ($ogImage)
  @section('og_image', $ogImage)
@endif

@section('content')
  <div class="container py-4">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}"
            class="text-decoration-none">{{ __('storefront.nav.home') }}</a></li>
        @if ($collection = $product->collections->first())
          <li class="breadcrumb-item">
            <a href="{{ $collection->defaultUrl?->slug ? route('storefront.collection', $collection->defaultUrl->slug) : '#' }}"
              class="text-decoration-none">{{ $collection->translateAttribute('name') }}</a>
          </li>
        @endif
        <li class="breadcrumb-item active" aria-current="page">{{ $name }}</li>
      </ol>
    </nav>

    {{-- Gallery + purchase panel — full SSR. Vanilla JS (enhance/product-variant.js)
         reads the embedded ProductResource ($state) to swap price/stock/gallery and
         set the add-to-cart variant when option values change. Crawlable, works
         no-JS (first variant pre-selected). PhotoSwipe lightbox: thumbnails use the
         `large` conversion; clicking opens `zoom`. --}}
    <div class="row g-4" data-product-detail data-slug="{{ $slug }}">
      <script type="application/json" data-product-state>@json($state)</script>
      {{-- Translated labels the variant enhancer writes on option change, so
             JS stays in the visitor's language (no hardcoded English in JS). --}}
      @php
        $productI18n = [
            'add_to_cart' => __('storefront.product.add_to_cart'),
            'out_of_stock' => __('storefront.product.out_of_stock'),
            'select_options' => __('storefront.product.select_options'),
            'in_stock' => __('storefront.product.in_stock', ['count' => '%d']),
        ];
      @endphp
      <script type="application/json" data-product-i18n>@json($productI18n)</script>

      <div class="col-12 col-lg-7">
        {{-- Swiper gallery: main slider + thumbnail strip. enhance/_gallery.js
                 inits Swiper and binds PhotoSwipe to the main slides (click → zoom),
                 and re-renders this when the chosen variant changes. The structure
                 below is the SSR fallback (a plain swiper, usable with no JS).
                 $galleryImages injected by the Media view composer (standards §7). --}}

        {{-- Thumbs strip sits to the LEFT of the main image (vertical on
                 desktop, horizontal under the main on mobile). --}}
        <div id="product-gallery" data-product-gallery class="product-gallery">
          @if ($galleryImages->isNotEmpty())
            <div class="swiper product-gallery__main" data-gallery-main>
              <div class="swiper-wrapper">
                @foreach ($galleryImages as $img)
                  <div class="swiper-slide">
                    <a href="{{ $img['zoom'] }}" class="d-block product-gallery__item rounded"
                      data-pswp-width="{{ $zoomSize['width'] }}" data-pswp-height="{{ $zoomSize['height'] }}"
                      target="_blank" rel="noreferrer">
                      @if ($img['picture'] ?? false)
                        @include('theme::components.picture', [
                            'picture' => $img['picture'],
                            'alt' => $name,
                            'class' => 'img-fluid rounded w-100',
                            'sizes' => '(min-width: 992px) 58vw, 100vw',
                            'loading' => $loop->first ? 'eager' : 'lazy',
                            'fetchpriority' => $loop->first ? 'high' : null,
                        ])
                      @else
                        <img src="{{ $img['large'] }}" alt="{{ $name }}" class="img-fluid rounded w-100"
                          loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                      @endif
                    </a>
                  </div>
                @endforeach
              </div>
              <div class="swiper-button-prev"></div>
              <div class="swiper-button-next"></div>
            </div>
            {{-- Thumbs strip first in source (sits LEFT on desktop / above on
                         mobile) — mirrors enhance/_gallery.js so the SSR markup and the
                         JS-rendered gallery match (same data-* order). --}}
            @if ($galleryImages->count() > 1)
              <div class="swiper product-gallery__thumbs" data-gallery-thumbs>
                <div class="swiper-wrapper">
                  @foreach ($galleryImages as $img)
                    <div class="swiper-slide">
                      <img src="{{ $img['small'] }}" alt="{{ $name }}" class="img-fluid rounded" loading="lazy">
                    </div>
                  @endforeach
                </div>
              </div>
            @endif
          @else
            <div class="ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted">
              No image
            </div>
          @endif
        </div>
      </div>

      <div class="col-12 col-lg-5">
        {{-- $sale (Promotion) + $displayPrice (Pricing) injected by composers. --}}
        <div class="d-flex align-items-start gap-2">
          <div class="flex-grow-1">
            @if ($brand = $product->brand?->name)
              <div class="text-muted text-uppercase small">{{ $brand }}</div>
            @endif
            <h1 class="h3">{{ $name }}</h1>
          </div>
          {{-- Promotion badge (same source as the product card). --}}
          @if ($sale)
            <span class="badge fs-6 {{ $sale['is_flash_sale'] ?? false ? 'bg-danger' : 'bg-dark' }}"
              @if ($sale['ends_at'] ?? false) data-promo-deadline="{{ $sale['ends_at'] }}" @endif>
              @if ($sale['is_flash_sale'] ?? false)
                <i class="bi bi-lightning-charge-fill me-1"></i>
              @endif{{ $sale['label'] }}
            </span>
          @endif
        </div>

        {{-- Price reflects the selected variant (updated by JS). On a price
                 break the original is struck and the sale price shown beside it;
                 enhance/product-variant.js keeps this in sync on variant change. --}}
        <div class="h4 my-3" data-product-price>
          @if ($sale && ($sale['has_price_break'] ?? false))
            <span class="text-danger me-2" data-price-sale>{{ $sale['sale'] }}</span>
            <span class="text-muted text-decoration-line-through fs-6" data-price-original>{{ $sale['original'] }}</span>
          @else
            <span data-price-sale>{{ $displayPrice }}</span>
          @endif
        </div>

        <div class="my-4">
          @php
            // Option groups (e.g. Size → [S,M,L]) derived from variants, in
            // a stable order, for the SSR buttons.
            $optionGroups = [];
            foreach ($product->variants as $variant) {
                foreach ($variant->values as $value) {
                    $optName = $value->option?->translate('name') ?? ($value->option?->name ?? 'Option');
                    $valName = $value->translate('name') ?? $value->name;
                    $optionGroups[$optName] = $optionGroups[$optName] ?? [];
                    if (!in_array($valName, $optionGroups[$optName], true)) {
                        $optionGroups[$optName][] = $valName;
                    }
                }
            }
          @endphp

          @foreach ($optionGroups as $optName => $values)
            <div class="mb-3" data-option-group="{{ $optName }}">
              <label class="form-label small text-uppercase d-block">{{ $optName }}</label>
              <div class="d-flex flex-wrap gap-2">
                @foreach ($values as $val)
                  <button type="button" class="btn btn-sm btn-outline-dark" data-option="{{ $optName }}"
                    data-value="{{ $val }}">{{ $val }}</button>
                @endforeach
              </div>
            </div>
          @endforeach

          <div class="small text-muted mb-3" data-product-stock>
            @if ($firstVariant && $firstVariant->stock > 0)
              {{ __('storefront.product.in_stock', ['count' => $firstVariant->stock]) }}
            @elseif($firstVariant)
              {{ __('storefront.product.out_of_stock') }}
            @endif
          </div>

          <form method="POST" action="{{ route('storefront.cart') }}" data-add-to-cart>
            @csrf
            <input type="hidden" name="variant_id" value="{{ $firstVariant?->id }}" data-variant-input>
            <button class="btn btn-dark btn-lg w-100" type="submit" data-add-to-cart-btn @disabled(!$firstVariant || $firstVariant->stock <= 0)>
              {{ $firstVariant && $firstVariant->stock > 0 ? __('storefront.product.add_to_cart') : __('storefront.product.out_of_stock') }}
            </button>
          </form>

          {{-- Back-in-stock: shown only when the selected variant is out of
                     stock. Subscribes via POST /api/v1/inventory/notify-me. The
                     variant id is kept in sync by notify-me.js (variant:changed).
                     Hidden by default; revealed by JS (no JS → add-to-cart above
                     already shows "Out of stock", which is the honest no-JS state). --}}
          <div class="notify-me mt-3" data-notify-me hidden
            @if ($firstVariant && $firstVariant->stock <= 0) data-initial-out="1" @endif>
            <p class="small text-muted mb-2">
              <i class="bi bi-bell me-1"></i>{{ __('storefront.product.notify_intro') }}
            </p>
            <form class="input-group" data-notify-form>
              <input type="hidden" name="variant_id" value="{{ $firstVariant?->id }}" data-notify-variant>
              <input type="email" name="email" class="form-control" required
                placeholder="{{ __('storefront.checkout.email') }}" data-notify-email
                value="{{ optional(auth()->user())->email }}">
              <button class="btn btn-outline-dark" type="submit" data-notify-submit>
                {{ __('storefront.product.notify_me') }}
              </button>
            </form>
            <div class="small mt-1" data-notify-status></div>
          </div>
        </div>

        @if ($description)
          <div class="mt-4">
            <h2 class="h6 text-uppercase">{{ __('storefront.product.description') }}</h2>
            <div class="text-muted" data-product-description>{!! $description !!}</div>
          </div>
        @endif
      </div>
    </div>

    {{-- Size chart stays in Blade (outside the island): it's static and the Vue
         island would otherwise wipe it on mount. Sits under the purchase panel. --}}
    <div class="row">
      <div class="col-12 col-lg-5 offset-lg-7">
        @include('theme::partials.size-chart', ['sizeChart' => $sizeChart, 'slug' => $slug])
      </div>
    </div>

    {{-- Related / You may also like --}}
    @if ($related->isNotEmpty())
      <section class="mt-5">
        <h2 class="h4 mb-3">{{ __('storefront.cart.you_may_also_like') }}</h2>
        <div class="row g-4">
          @foreach ($related as $item)
            <div class="col-6 col-md-4 col-lg-3">
              @include('theme::components.product-card', ['product' => $item])
            </div>
          @endforeach
        </div>
      </section>
    @endif

    {{-- Recently viewed — personalised (localStorage), so it's an enhancer that
         records this product then renders the rest. Records `$slug`; the strip
         excludes the current product. Hidden until JS finds ≥1 other item. --}}
    @include('theme::partials.recently-viewed', ['currentSlug' => $slug])
  </div>
@endsection

@push('head')
  @php
    $currency = $currencyCode; // injected by the Pricing view composer (standards §7)
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $name,
        'description' => \Illuminate\Support\Str::limit(strip_tags((string) $description), 300),
        'sku' => $firstVariant?->sku,
        'image' => $ogImage ? [$ogImage] : [],
        'brand' => $product->brand?->name ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' =>
            $priceAmount !== null
                ? [
                    '@type' => 'Offer',
                    'price' => (string) $priceAmount,
                    'priceCurrency' => $currency,
                    'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => url()->current(),
                ]
                : null,
    ];
    $breadcrumbItems = [['name' => 'Home', 'url' => route('storefront.home')]];
    if ($collection = $product->collections->first()) {
        $breadcrumbItems[] = [
            'name' => $collection->translateAttribute('name'),
            'url' => $collection->defaultUrl?->slug
                ? route('storefront.collection', $collection->defaultUrl->slug)
                : url()->current(),
        ];
    }
    $breadcrumbItems[] = ['name' => $name, 'url' => url()->current()];
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($breadcrumbItems)
            ->map(
                fn($item, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => $item['url'],
                ],
            )
            ->all(),
    ];
  @endphp
  <script type="application/ld+json">@json(array_filter($jsonLd), JSON_UNESCAPED_SLASHES)</script>
  <script type="application/ld+json">@json($breadcrumbLd, JSON_UNESCAPED_SLASHES)</script>

  {{-- PhotoSwipe lightbox styles (public/vendor). --}}
  <link rel="stylesheet" href="{{ asset('vendor/photoswipe/photoswipe.css') }}">
@endpush

@push('scripts')
  {{-- PhotoSwipe core + lightbox as UMD globals (window.PhotoSwipe /
     window.PhotoSwipeLightbox). enhance/_gallery.js reads these globals and
     (re)initialises the lightbox on load and whenever the chosen variant — and
     thus the visible image set — changes. --}}
  <script src="{{ asset('vendor/photoswipe/photoswipe.umd.min.js') }}"></script>
  <script src="{{ asset('vendor/photoswipe/photoswipe-lightbox.umd.min.js') }}"></script>
@endpush
