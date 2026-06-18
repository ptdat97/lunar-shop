@extends('theme::layouts.app')

@php
    use Lunar\Facades\Pricing;

    $name = $product->translateAttribute('name');
    $description = $product->translateAttribute('description');
    $brand = $product->brand?->name;

    // Gallery: product media (fallback to thumbnail or a placeholder).
    $media = $product->media ?? collect();
    $images = $media->isNotEmpty()
        ? $media->map(fn ($m) => $m->hasGeneratedConversion('large') ? $m->getUrl('large') : $m->getUrl())->all()
        : [$product->thumbnail?->getUrl('large') ?? '/themes/modave/images/products/womens/women-3.jpg'];

    // Variants for the picker + pricing.
    $variants = $product->variants->map(function ($v) {
        $price = Pricing::for($v)->get()->matched->price;
        return [
            'id' => $v->id,
            'sku' => $v->sku,
            'stock' => $v->stock,
            'price' => $price->decimal(),
            'price_formatted' => (string) $price->formatted(),
        ];
    })->values();

    $firstVariant = $variants->first();
@endphp

@section('title', $name)

@section('content')
    <section class="tf-main-product section-image-zoom">
        <div class="container">
            <div class="row">
                {{-- Media gallery: vertical thumbs + main swiper + hover/lightbox zoom --}}
                <div class="col-md-6">
                    <div class="tf-product-media-wrap sticky-top">
                        <div class="thumbs-slider">
                            <div dir="ltr" class="swiper tf-product-media-thumbs other-image-zoom" data-direction="vertical">
                                <div class="swiper-wrapper stagger-wrap">
                                    @foreach ($images as $img)
                                        <div class="swiper-slide stagger-item">
                                            <div class="item">
                                                <img class="lazyload" data-src="{{ $img }}" src="{{ $img }}" alt="{{ $name }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div dir="ltr" class="swiper tf-product-media-main" id="gallery-swiper-started">
                                <div class="swiper-wrapper">
                                    @foreach ($images as $img)
                                        <div class="swiper-slide">
                                            <a href="{{ $img }}" target="_blank" class="item" data-pswp-width="600px" data-pswp-height="800px">
                                                <img class="tf-image-zoom lazyload" data-zoom="{{ $img }}" data-src="{{ $img }}" src="{{ $img }}" alt="{{ $name }}">
                                            </a>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="swiper-button-next button-style-arrow thumbs-next"></div>
                                <div class="swiper-button-prev button-style-arrow thumbs-prev"></div>
                            </div>
                        </div>
                        {{-- Drift zoom pane (where the magnified image renders on hover) --}}
                        <div class="tf-zoom-main"></div>
                    </div>
                </div>

                {{-- Info --}}
                <div class="col-md-6">
                    <div class="tf-product-info-wrap position-relative">
                        <div class="tf-product-info-list">
                            <div class="tf-product-info-heading">
                                @if ($brand)
                                    <div class="tf-product-info-name">
                                        <div class="text text-btn-uppercase">{{ $brand }}</div>
                                    </div>
                                @endif
                                <h3 class="name">{{ $name }}</h3>
                                <div class="tf-product-info-price">
                                    <h5 class="price-on-sale font-2" data-vue-price>{{ $firstVariant['price_formatted'] ?? '' }}</h5>
                                </div>
                            </div>

                            @if ($description)
                                <div class="tf-product-description">
                                    <p>{{ $description }}</p>
                                </div>
                            @endif

                            {{-- Vue island: variant picker + quantity + add to cart --}}
                            <div data-vue="product-purchase" data-variants='@json($variants)'></div>

                            {{-- Size & Fit (opens popup) --}}
                            <div class="mt_20">
                                <x-theme::size-chart :size-chart="$sizeChart ?? []" :slug="$slug ?? ''" />
                            </div>

                            <div class="tf-product-info-extra-link mt_20">
                                <a href="/search" class="tf-product-extra-icon">
                                    <span class="text">Continue shopping</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Description --}}
    @if ($description)
        <section class="flat-spacing">
            <div class="container">
                <div class="widget-tabs style-has-border">
                    <ul class="widget-menu-tab">
                        <li class="item-title active"><span class="inner">Description</span></li>
                    </ul>
                    <div class="widget-content-tab">
                        <div class="widget-content-inner active">
                            <p>{{ $description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- Related products --}}
    @if (($related ?? collect())->isNotEmpty())
        <section class="flat-spacing pt_0">
            <div class="container">
                <div class="heading-section text-center wow fadeInUp">
                    <h3 class="heading">You may also like</h3>
                </div>
                <div class="swiper tf-sw-latest" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-space-lg="30" data-space="15">
                    <div class="swiper-wrapper">
                        @foreach ($related as $item)
                            <div class="swiper-slide">
                                <x-theme::product-card :product="$item" />
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif
@endsection

@push('head')
    <link rel="stylesheet" href="/themes/modave/css/drift-basic.min.css">
    <link rel="stylesheet" href="/themes/modave/css/photoswipe.css">
@endpush

@push('scripts')
    <script src="/themes/modave/js/drift.min.js"></script>
    <script type="module" src="/themes/modave/js/zoom.js"></script>
@endpush
