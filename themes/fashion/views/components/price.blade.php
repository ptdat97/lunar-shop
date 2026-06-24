{{-- Display price for a product. Included with ['product' => $product].
     Resolves from the first variant via Lunar Pricing — the same engine the API
     (ProductVariantResource) uses, so SSR price matches the API.

     When an automatic promotion gives this product a price break, the original
     price is struck through and the discounted price shown beside it. --}}
@php
    $variant = $product->variants->first();
    $formatted = null;
    if ($variant) {
        try {
            $formatted = (string) \Lunar\Facades\Pricing::for($variant)->get()->matched->price->formatted();
        } catch (\Throwable $e) {
            $formatted = null;
        }
    }

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
