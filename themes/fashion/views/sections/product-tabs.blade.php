@php
    // $tabs (each: ['label' => …, 'products' => Collection]) is injected by the
    // product-tabs section provider (ContentServiceProvider). Legacy fallback:
    // if no resolved tabs, wrap the flat $products list in a single tab.
    $tabs = collect($tabs ?? []);
    if ($tabs->isEmpty() && !empty($products) && $products->isNotEmpty()) {
        $tabs = collect([['label' => $settings['heading'] ?? '', 'products' => $products]]);
    }
    // Only tabs that actually have products to show.
    $tabs = $tabs->filter(fn ($t) => ($t['products'] ?? collect())->isNotEmpty())->values();
@endphp
@if($tabs->isNotEmpty())
    <section class="container my-5 product-tabs" data-product-tabs>
        <div class="section-head section-head--center">
            @if(!empty($settings['kicker']))
                <span class="eyebrow">{{ $settings['kicker'] }}</span>
            @endif
            @if(!empty($settings['heading']))
                <h2 class="display-heading">{{ $settings['heading'] }}</h2>
            @endif
        </div>

        @if($tabs->count() > 1)
            <ul class="product-tabs__nav" role="tablist">
                @foreach($tabs as $i => $tab)
                    <li>
                        <button class="product-tabs__tab {{ $i === 0 ? 'is-active' : '' }}" type="button"
                                role="tab" aria-selected="{{ $i === 0 ? 'true' : 'false' }}"
                                aria-controls="product-tab-panel-{{ $i }}" data-tab-target="{{ $i }}">
                            {{ $tab['label'] ?? '' }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Every tab's grid is rendered server-side (crawlable, works no-JS);
             enhance/product-tabs.js just toggles which one is visible. --}}
        @foreach($tabs as $i => $tab)
            <div class="row g-4 product-tabs__panel" id="product-tab-panel-{{ $i }}"
                 role="tabpanel" data-tab-panel="{{ $i }}" @if($i !== 0) hidden @endif>
                @foreach($tab['products'] as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('theme::components.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        @endforeach
    </section>
@endif
