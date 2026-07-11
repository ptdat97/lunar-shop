{{-- Flash-sale section. Data ($flashSale, $flashSaleDescription, $products)
     comes from the SectionBuilder data provider (PromotionService) — renders
     nothing when no flash sale is running. The countdown ticks client-side via
     enhance/flash-sale.js (reads data-ends-at); the SSR markup stays crawlable. --}}
@php
    $flashSale = $flashSale ?? null;
    $products = $products ?? collect();
    $heading = trim((string) ($settings['heading'] ?? '')) ?: $flashSale?->name;
    // Flash sales are displayable promotions, so a handle links to its own page.
    $viewAllUrl = $flashSale?->handle
        ? route('storefront.promotion', $flashSale->handle)
        : route('storefront.promotions');
@endphp

@if($flashSale)
    <section class="flash-sale my-5"
             data-flash-sale
             data-ends-at="{{ $flashSale->ends_at?->toIso8601String() }}"
             data-ended-text="{{ __('storefront.promotion.ended') }}">
        <div class="container">
            <div class="flash-sale__band rounded p-4 p-lg-5">
                <div class="d-flex align-items-end justify-content-between mb-4 gap-3 flex-wrap">
                    <div>
                        <h2 class="h4 mb-1 text-white">
                            <i class="bi bi-lightning-charge-fill text-warning"></i> {{ $heading }}
                        </h2>
                        @if($flashSaleDescription)
                            <p class="mb-0 text-white opacity-75">{{ $flashSaleDescription }}</p>
                        @endif
                    </div>

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        @if($flashSale->ends_at)
                            <div class="d-inline-flex align-items-center gap-2">
                                <span class="text-white opacity-75 small text-uppercase">{{ __('storefront.promotion.ends_in') }}</span>
                                <span class="flash-sale__countdown badge bg-warning text-dark font-monospace" data-flash-countdown>—</span>
                            </div>
                        @endif
                        <a href="{{ $viewAllUrl }}" class="btn btn-light btn-sm flex-shrink-0">
                            {{ __('storefront.common.view_all') }} <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                @if($products->isNotEmpty())
                    {{-- Swiper is initialised by enhance/sliders.js (same enhancer as the
                         promotion slider — this section reuses its breakpoints/nav). --}}
                    <div class="swiper" data-promotion-swiper>
                        <div class="swiper-wrapper">
                            @foreach($products as $product)
                                <div class="swiper-slide h-auto">
                                    @include('theme::components.product-card', ['product' => $product])
                                </div>
                            @endforeach
                        </div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
