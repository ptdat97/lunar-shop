{{-- Display price for a product. Included with ['product' => $product].
     Price + promotion come from presentation services (no pricing/discount
     logic in the view) — the same engines the API uses, so SSR matches the API.

     When an automatic promotion gives this product a price break, the original
     price is struck through and the discounted price shown beside it. --}}
@php
    $formatted = app(\Modules\Pricing\Services\PricingService::class)->displayPrice($product);
    $sale = app(\Modules\Promotion\Services\PromotionService::class)->saleFor($product);
@endphp
@if($formatted)
    @if($sale && ($sale['has_price_break'] ?? false))
        <span class="product-card__price fw-semibold text-danger me-1">{{ $sale['sale'] }}</span>
        <span class="product-card__price-original text-muted text-decoration-line-through small">{{ $sale['original'] }}</span>
    @else
        <span class="product-card__price fw-semibold">{{ $formatted }}</span>
    @endif
@endif
