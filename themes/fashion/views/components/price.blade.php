{{-- Display price for a product. Included with ['product' => $product].
     $formatted (Pricing) + $sale (Promotion) are injected by view composers
     (no service resolution / pricing logic in the view — standards §7).

     When an automatic promotion gives this product a price break, the original
     price is struck through and the discounted price shown beside it. --}}
@if($formatted)
    @if($sale && ($sale['has_price_break'] ?? false))
        <span class="product-card__price fw-semibold text-danger me-1">{{ $sale['sale'] }}</span>
        <span class="product-card__price-original text-muted text-decoration-line-through small">{{ $sale['original'] }}</span>
    @else
        <span class="product-card__price fw-semibold">{{ $formatted }}</span>
    @endif
@endif
