{{-- Shoppable hotspot pins over a lookbook photo. Included with ['pins' => …]
     (a collection of LookbookItem with pos_x/pos_y set). Each pin shows a dot at
     its percentage position; hover/focus reveals a mini product card with an
     add-to-cart button (reuses the delegated add-to-cart.js: button[data-add-to-cart]).
     Purely additive — products are also listed in full below, so no-JS users
     still shop everything. --}}
@php $pins = $pins ?? collect(); @endphp
@foreach($pins as $pin)
    @php
        $product = $pin->product;
        $variant = $product?->variants->first();
        $slug = $product?->defaultUrl?->slug;
        $url = $slug ? route('storefront.product', $slug) : '#';
    @endphp
    @continue(! $product)
    <div class="lookbook-pin" style="left: {{ $pin->pos_x }}%; top: {{ $pin->pos_y }}%;">
        <button type="button" class="lookbook-pin__dot" aria-label="{{ $product->translateAttribute('name') }}"></button>
        <div class="lookbook-pin__card">
            <a href="{{ $url }}" class="d-flex align-items-center gap-2 text-decoration-none text-dark">
                @php $thumb = $product->thumbnail; @endphp
                <span class="lookbook-pin__name">{{ $product->translateAttribute('name') }}</span>
            </a>
            <div class="d-flex align-items-center justify-content-between mt-1 gap-2">
                @include('theme::components.price', ['product' => $product])
                @if($variant)
                    <button type="button" class="btn btn-sm btn-dark"
                            data-add-to-cart data-variant-id="{{ $variant->id }}"
                            aria-label="{{ __('storefront.product.add_to_cart') }}">
                        <i class="bi bi-bag-plus"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>
@endforeach
