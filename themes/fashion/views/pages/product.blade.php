@extends('theme::layouts.app')

@php
    $name = $product->translateAttribute('name');
    $description = $product->translateAttribute('description');
    // Hydration payload for the variant island (Bước 4) — same ProductResource
    // shape as GET /api/v1/products/{slug}. SSR below renders without it.
    $state = (new \Modules\Product\Http\Resources\ProductResource(
        $product->loadMissing(['variants.values.option', 'variants.images', 'media'])
    ))->resolve();

    $media = $product->media ?? collect();
    $firstVariant = $product->variants->first();

    // PhotoSwipe needs the pixel dimensions of the full-size (zoom) image.
    // Every conversion is Fit::Crop to the configured size, so all zoom images
    // share the admin-configured zoom width/height.
    $zoomSize = app(\Modules\Media\Services\MediaSettings::class)->sizes()['zoom'];

    // OG image: first media's `large` conversion, generated on demand if missing.
    $ogImage = app(\Modules\Media\Services\MediaUrl::class)->conversion($media->first(), 'large');

    // Lowest variant price for structured data, via the same Lunar Pricing
    // engine the API uses (resolve() keeps $state['variants'] as resource
    // objects, so we read prices straight from the models instead).
    $priceAmount = $product->variants->map(function ($variant) {
        try { return \Lunar\Facades\Pricing::for($variant)->get()->matched->price->decimal(); }
        catch (\Throwable $e) { return null; }
    })->filter()->min();
    $inStock = $product->variants->sum('stock') > 0;
@endphp

@section('title', $name.' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $description), 155))
@section('og_type', 'product')
@if($ogImage)
    @section('og_image', $ogImage)
@endif

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">Home</a></li>
            @if($collection = $product->collections->first())
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

        <div class="col-12 col-lg-7">
            <div class="row g-2" id="product-gallery" data-pswp-gallery>
                @forelse($media as $image)
                    @php
                        // `large`/`zoom` conversions, generated on demand if missing.
                        $urls = app(\Modules\Media\Services\MediaUrl::class);
                        $large = $urls->conversion($image, 'large');
                        $zoom = $urls->conversion($image, 'zoom') ?? $large;
                    @endphp
                    @if($large)
                        <div class="{{ $loop->first ? 'col-12' : 'col-6' }}">
                            <a href="{{ $zoom }}" class="d-block product-gallery__item rounded"
                               data-pswp-width="{{ $zoomSize['width'] }}"
                               data-pswp-height="{{ $zoomSize['height'] }}"
                               target="_blank" rel="noreferrer">
                                <img src="{{ $large }}" alt="{{ $name }}" class="img-fluid rounded w-100"
                                     loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                            </a>
                        </div>
                    @endif
                @empty
                    <div class="col-12 ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted">
                        No image
                    </div>
                @endforelse
            </div>
        </div>

        <div class="col-12 col-lg-5">
            @if($brand = $product->brand?->name)
                <div class="text-muted text-uppercase small">{{ $brand }}</div>
            @endif
            <h1 class="h3">{{ $name }}</h1>

            {{-- Price reflects the selected variant (updated by JS); SSR shows the
                 first variant's price. --}}
            <div class="h4 my-3" data-product-price>{{ $firstVariant?->prices->first()?->price->formatted() }}</div>

            <div class="my-4">
                @php
                    // Option groups (e.g. Size → [S,M,L]) derived from variants, in
                    // a stable order, for the SSR buttons.
                    $optionGroups = [];
                    foreach ($product->variants as $variant) {
                        foreach ($variant->values as $value) {
                            $optName = $value->option?->translate('name') ?? $value->option?->name ?? 'Option';
                            $valName = $value->translate('name') ?? $value->name;
                            $optionGroups[$optName] = $optionGroups[$optName] ?? [];
                            if (! in_array($valName, $optionGroups[$optName], true)) {
                                $optionGroups[$optName][] = $valName;
                            }
                        }
                    }
                @endphp

                @foreach($optionGroups as $optName => $values)
                    <div class="mb-3" data-option-group="{{ $optName }}">
                        <label class="form-label small text-uppercase d-block">{{ $optName }}</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($values as $val)
                                <button type="button" class="btn btn-sm btn-outline-dark"
                                        data-option="{{ $optName }}" data-value="{{ $val }}">{{ $val }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="small text-muted mb-3" data-product-stock>
                    @if($firstVariant && $firstVariant->stock > 0){{ $firstVariant->stock }} in stock @elseif($firstVariant)Out of stock @endif
                </div>

                <form method="POST" action="{{ route('storefront.cart') }}" data-add-to-cart>
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $firstVariant?->id }}" data-variant-input>
                    <button class="btn btn-dark btn-lg w-100" type="submit" data-add-to-cart-btn
                            @disabled(!$firstVariant || $firstVariant->stock <= 0)>
                        {{ $firstVariant && $firstVariant->stock > 0 ? 'Add to cart' : 'Out of stock' }}
                    </button>
                </form>
            </div>

            @if($description)
                <div class="mt-4">
                    <h2 class="h6 text-uppercase">Description</h2>
                    <div class="text-muted">{!! $description !!}</div>
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
    @if($related->isNotEmpty())
        <section class="mt-5">
            <h2 class="h4 mb-3">You may also like</h2>
            <div class="row g-4">
                @foreach($related as $item)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('theme::components.product-card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('head')
@php
    $currency = \Lunar\Models\Currency::getDefault()?->code ?? 'USD';
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $name,
        'description' => \Illuminate\Support\Str::limit(strip_tags((string) $description), 300),
        'sku' => $firstVariant?->sku,
        'image' => $ogImage ? [$ogImage] : [],
        'brand' => $product->brand?->name ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => $priceAmount !== null ? [
            '@type' => 'Offer',
            'price' => (string) $priceAmount,
            'priceCurrency' => $currency,
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => url()->current(),
        ] : null,
    ];
    $breadcrumbItems = [['name' => 'Home', 'url' => route('storefront.home')]];
    if ($collection = $product->collections->first()) {
        $breadcrumbItems[] = [
            'name' => $collection->translateAttribute('name'),
            'url' => $collection->defaultUrl?->slug ? route('storefront.collection', $collection->defaultUrl->slug) : url()->current(),
        ];
    }
    $breadcrumbItems[] = ['name' => $name, 'url' => url()->current()];
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($breadcrumbItems)->map(fn ($item, $i) => [
            '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'item' => $item['url'],
        ])->all(),
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
