{{-- Promotion slider section. Data ($products, $pinnedPromotion) comes from the
     SectionBuilder data provider (PromotionService) — presentation only here.
     Each slide is the canonical product card (badge + struck price). "View all"
     links to the promotion page(s). --}}
@php
    $products = $products ?? collect();
    $heading = $settings['heading'] ?? 'On Sale Now';
    $subheading = $settings['subheading'] ?? null;
    // Pinned to one promotion → link straight to its detail page; else the index.
    $viewAllUrl = ($pinnedPromotion ?? null)
        ? route('storefront.promotion', $pinnedPromotion->handle)
        : route('storefront.promotions');
@endphp

@if($products->isNotEmpty())
    <section class="promotion-slider container my-5">
        <div class="d-flex align-items-end justify-content-between mb-4 gap-3 flex-wrap">
            <div>
                <h2 class="h4 mb-1">
                    <i class="bi bi-tags-fill text-danger"></i> {{ $heading }}
                </h2>
                @if($subheading)
                    <p class="text-muted mb-0">{{ $subheading }}</p>
                @endif
            </div>
            <a href="{{ $viewAllUrl }}" class="btn btn-outline-dark btn-sm flex-shrink-0">
                View all <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        {{-- Swiper is initialised by enhance/sliders.js (vanilla enhancer). This
             section is pre-rendered by SectionRenderer in isolation, so inline
             @push('scripts') here would never reach the layout's @stack. --}}
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
    </section>
@endif
