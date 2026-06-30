{{-- Flash-sale promo bar. Rendered SSR (crawlable); $flashSale +
     $flashSaleDescription are supplied by Promotion's view composer
     (standards §7). The countdown is enhanced client-side by enhance/flash-sale.js. --}}
@php($flashSale = $flashSale ?? null)

@if($flashSale)
    <div class="promo-bar bg-dark text-white py-2" data-flash-sale data-ends-at="{{ $flashSale->ends_at?->toIso8601String() }}">
        <div class="container d-flex flex-wrap align-items-center justify-content-center gap-2 small text-center">
            <span class="fw-semibold text-uppercase">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
                {{ $flashSale->name }}
            </span>
            <span class="opacity-75">{{ $flashSaleDescription }}</span>
            @if($flashSale->ends_at)
                <span class="d-inline-flex align-items-center gap-1">
                    <span class="opacity-75">{{ __('storefront.promotion.ends_in') }}</span>
                    <span class="badge bg-warning text-dark font-monospace" data-flash-countdown>—</span>
                </span>
            @endif
        </div>
    </div>
@endif
