@extends('theme::layouts.app')
@section('body_class', 'page-lookbook')

@section('title', $lookbook->title.' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $lookbook->description), 155))
@section('canonical', url()->current())

@section('content')
    @php
        // $fileUrl resolver injected by the FileManager view composer (§7).
        $cover = $fileUrl($lookbook->cover_image, 'large');
        $images = $lookbook->images;
        $items = $lookbook->items->filter(fn ($i) => $i->product)->values();
        $products = $items->map->product->values();

        // Hotspot pins grouped by the image they sit on (null image_id = cover).
        $pinsByImage = $items->filter->isHotspot()->groupBy('image_id');
        $coverPins = $pinsByImage->get(null, collect());

        // If there's no cover image, pins meant for it have nowhere to show — pin
        // them on the first gallery image instead so they aren't lost.
        $fallbackImageId = (! $cover && $images->isNotEmpty()) ? $images->first()->id : null;

        // Variant ids for "shop the set" (first variant of each product).
        $setVariantIds = $products->map(fn ($p) => $p->variants->first()?->id)->filter()->values();
    @endphp

    {{-- Hero / cover with hotspots --}}
    <header class="lookbook-hero position-relative mb-4">
        @if($cover)
            <div class="lookbook-hero__media position-relative" data-lookbook-stage>
                <img src="{{ $cover }}" alt="{{ $lookbook->title }}" class="w-100" style="object-fit:cover" loading="eager">
                @include('theme::partials.lookbook-pins', ['pins' => $coverPins])
            </div>
        @endif
        <div class="container py-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">{{ __('storefront.nav.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('storefront.lookbooks') }}" class="text-decoration-none">{{ __('storefront.lookbook.title') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $lookbook->title }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-2">{{ $lookbook->title }}</h1>
            @if($lookbook->description)
                <p class="text-muted mb-3">{{ $lookbook->description }}</p>
            @endif

            {{-- Shop the whole set in one click. Real link to the lookbook section
                 as no-JS fallback; the enhancer adds all variants to the cart. --}}
            @if($setVariantIds->isNotEmpty())
                <button type="button" class="btn btn-dark"
                        data-lookbook-add-set data-variant-ids="{{ $setVariantIds->join(',') }}">
                    <i class="bi bi-bag-plus me-1"></i>{{ __('storefront.lookbook.shop_the_set') }}
                    <span class="opacity-75">({{ $setVariantIds->count() }})</span>
                </button>
                <span class="small ms-2" data-lookbook-set-status></span>
            @endif
        </div>
    </header>

    {{-- Photo gallery with per-image hotspots --}}
    @if($images->isNotEmpty())
        <section class="container mb-5">
            <div class="lookbook-gallery">
                @foreach($images as $image)
                    @php $src = $fileUrl($image->image, 'large'); @endphp
                    @if($src)
                        <figure class="lookbook-gallery__item mb-0 position-relative" data-lookbook-stage>
                            <img src="{{ $src }}" alt="{{ $image->caption ?? $lookbook->title }}"
                                 class="w-100 rounded" loading="lazy">
                            @php
                                $imagePins = $pinsByImage->get($image->id, collect());
                                // Adopt the orphaned cover pins on the first gallery image.
                                if ($image->id === $fallbackImageId) {
                                    $imagePins = $imagePins->merge($coverPins);
                                }
                            @endphp
                            @include('theme::partials.lookbook-pins', ['pins' => $imagePins])
                            @if($image->caption)
                                <figcaption class="small text-muted mt-1">{{ $image->caption }}</figcaption>
                            @endif
                        </figure>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Products at the bottom --}}
    @if($products->isNotEmpty())
        <section class="container my-5">
            <h2 class="h4 mb-4">{{ __('storefront.lookbook.shop_this') }}</h2>
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('theme::components.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
