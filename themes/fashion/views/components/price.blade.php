{{-- Display price for a product. Included with ['product' => $product].
     Resolves from the first variant via Lunar Pricing — the same engine the API
     (ProductVariantResource) uses, so SSR price matches the API. --}}
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
@endphp
@if($formatted)
    <span class="product-card__price fw-semibold">{{ $formatted }}</span>
@endif
