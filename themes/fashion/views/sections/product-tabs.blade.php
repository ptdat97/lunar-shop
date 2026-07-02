@php $products = $products ?? collect(); @endphp
@if($products->isNotEmpty())
    <section class="container my-5 product-tabs">
        @php $tabs = $settings['tabs'] ?? []; @endphp
        <div class="section-head section-head--center">
            @if(!empty($settings['kicker']))
                <span class="eyebrow">{{ $settings['kicker'] }}</span>
            @endif
            @if(!empty($settings['heading']))
                <h2 class="display-heading">{{ $settings['heading'] }}</h2>
            @endif
        </div>

        @if($tabs)
            <ul class="product-tabs__nav" role="tablist">
                @foreach($tabs as $i => $tab)
                    <li>
                        {{-- Bước 1: all tabs show the same SSR product set; per-tab
                             querying lands with the catalog work in Bước 2. --}}
                        <button class="product-tabs__tab {{ $i === 0 ? 'is-active' : '' }}" type="button">
                            {{ $tab['label'] ?? '' }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="row g-4">
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('theme::components.product-card', ['product' => $product])
                </div>
            @endforeach
        </div>
    </section>
@endif
