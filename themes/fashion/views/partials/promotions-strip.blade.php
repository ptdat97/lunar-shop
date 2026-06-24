{{-- Active automatic promotions strip (SSR, crawlable). Shows flash sale +
     quantity/combo/membership deals the shopper benefits from without a code.
     Self-contained: resolves PromotionService directly (like product-card does
     with MediaUrl), so no controller wiring is needed to drop it on a page. --}}
@php
    $promotions = app(\Modules\Promotion\Services\PromotionService::class);
    $list = $promotions->activeAutomatic();
@endphp

@if($list->isNotEmpty())
<section class="promotions-strip py-4 bg-light">
    <div class="container">
        <h2 class="h5 text-uppercase mb-3">
            <i class="bi bi-tags-fill"></i> Today's deals
        </h2>
        <div class="row g-3">
            @foreach($list as $promo)
                @php($isFlash = (bool) (($promo->data ?? [])['flash_sale'] ?? false))
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="border rounded p-3 h-100 bg-white {{ $isFlash ? 'border-warning' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            @if($isFlash)
                                <i class="bi bi-lightning-charge-fill text-warning"></i>
                            @else
                                <i class="bi bi-gift text-dark"></i>
                            @endif
                            <span class="fw-semibold">{{ $promo->name }}</span>
                        </div>
                        <div class="text-muted small">{{ $promotions->describe($promo) }}</div>
                        @if($promo->ends_at)
                            <div class="small text-warning-emphasis mt-1">
                                Ends {{ $promo->ends_at->format('M j, H:i') }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
