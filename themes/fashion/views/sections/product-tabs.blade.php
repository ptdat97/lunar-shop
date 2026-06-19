@php $products = $products ?? collect(); @endphp
@if($products->isNotEmpty())
    <section class="container my-5">
        @php $tabs = $settings['tabs'] ?? []; @endphp
        @if($tabs)
            <ul class="nav nav-pills justify-content-center mb-4">
                @foreach($tabs as $i => $tab)
                    <li class="nav-item">
                        {{-- Bước 1: all tabs show the same SSR product set; per-tab
                             querying lands with the catalog work in Bước 2. --}}
                        <button class="nav-link {{ $i === 0 ? 'active' : '' }}" type="button">
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
