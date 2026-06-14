@php
    $products = $products ?? collect();
    // Tabs are presentational; all currently show the same product list.
    // Hook up real "best seller / on sale" queries later via the data provider.
    $tabs = $settings['tabs'] ?? [
        ['id' => 'newArrivals', 'label' => 'New Arrivals'],
        ['id' => 'bestSeller', 'label' => 'Best Seller'],
        ['id' => 'onSale', 'label' => 'On Sale'],
    ];
@endphp

<section class="flat-spacing pt-0">
    <div class="container">
        <div class="flat-animate-tab">
            <ul class="tab-product justify-content-sm-center wow fadeInUp" role="tablist">
                @foreach ($tabs as $i => $tab)
                    <li class="nav-tab-item" role="presentation">
                        <a href="#{{ $tab['id'] }}" class="{{ $i === 0 ? 'active' : '' }}" data-bs-toggle="tab">{{ $tab['label'] }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content wow fadeInUp">
                @foreach ($tabs as $i => $tab)
                    <div class="tab-pane {{ $i === 0 ? 'active show' : '' }}" id="{{ $tab['id'] }}" role="tabpanel">
                        <div class="tf-grid-layout tf-col-2 lg-col-3 xl-col-4">
                            @forelse ($products as $product)
                                <x-theme::product-card :product="$product" />
                            @empty
                                <p class="text-center">No products yet.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
