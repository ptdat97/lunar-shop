@extends('theme::layouts.app')

@php
    $name = $collection->translateAttribute('name');
    $description = $collection->translateAttribute('description');
    $currentSort = $currentSort ?? request('sort', 'best-selling');
    $sortLabels = [
        'best-selling' => 'Best selling',
        'a-z' => 'Alphabetically, A-Z',
        'z-a' => 'Alphabetically, Z-A',
        'price-low-high' => 'Price, low to high',
        'price-high-low' => 'Price, high to low',
    ];
    $facets = $facets ?? ['size' => [], 'color' => []];
    $meta = $state['meta'] ?? ['total' => $products->count(), 'page' => 1, 'last_page' => 1];
@endphp

@section('title', $name)

@section('content')
    {{-- Page title --}}
    <div class="page-title" style="background-image: url(/themes/modave/images/section/page-title.jpg);">
        <div class="container-full">
            <div class="row">
                <div class="col-12">
                    <h3 class="heading text-center">{{ $name }}</h3>
                    <ul class="breadcrumbs d-flex align-items-center justify-content-center">
                        <li><a class="link" href="{{ route('storefront.home') }}">Homepage</a></li>
                        <li><i class="icon-arrRight"></i></li>
                        <li>{{ $name }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            @if ($description)
                <p class="text-center mb_20">{{ $description }}</p>
            @endif

            {{--
                SSR-FIRST collection shop (Blade SSR + vanilla JS enhancement).
                1. SSR shell: the facet sidebar + product grid + sort below render
                   server-side — crawlable, no JS-flash, works with JS off (real
                   GET links/form).
                2. JSON Resource: $state (same shape as /api/v1/search) is embedded
                   so the enhancer starts from the rendered state, no initial fetch.
                3. JS enhancement: enhance/collection-shop.js intercepts facet/sort/
                   page changes, calls the API and re-renders the grid in place.
            --}}
            <div
                data-collection-shop
                data-scope="{{ $collection->defaultUrl?->slug }}"
                data-current-sort="{{ $currentSort }}"
            >
                {{-- Hydration payload (parsed by the enhancer; never shown) --}}
                <script type="application/json" data-island-state>@json($state)</script>

                {{-- Sort + count control bar (SSR). Falls back to a GET form with no JS. --}}
                <div class="tf-shop-control d-flex justify-content-between align-items-center mb_20">
                    <div class="text-secondary" data-result-count>{{ $meta['total'] }} products</div>
                    <form method="get" class="d-flex align-items-center gap-2" data-sort-form>
                        <label class="text-secondary" for="ssr-sort">Sort by</label>
                        <select id="ssr-sort" name="sort" class="form-select w-auto" data-sort onchange="this.form.submit()">
                            @foreach ($sortLabels as $value => $label)
                                <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="row">
                    {{-- Facet sidebar (SSR). Each value is a real filter link for no-JS;
                         JS upgrades them to multi-select toggles. --}}
                    <aside class="col-lg-3" data-facets>
                        @php($activeFilters = (array) request('filters', []))
                        @foreach (['size' => 'Size', 'color' => 'Color'] as $group => $groupLabel)
                            @if (! empty($facets[$group]))
                                <div class="facet-group mb_20">
                                    <h6 class="mb_12">{{ $groupLabel }}</h6>
                                    <ul class="facet-values list-unstyled">
                                        @foreach ($facets[$group] as $facet)
                                            @php($isActive = in_array($facet['value'], (array) ($activeFilters[$group] ?? []), true))
                                            <li>
                                                <a href="?{{ http_build_query(['filters' => [$group => [$facet['value']]], 'sort' => $currentSort]) }}"
                                                   class="link d-flex justify-content-between @if($isActive) active @endif"
                                                   data-facet data-group="{{ $group }}" data-value="{{ $facet['value'] }}"
                                                   aria-pressed="{{ $isActive ? 'true' : 'false' }}">
                                                    <span>{{ $facet['value'] }}</span>
                                                    <span class="text-secondary">({{ $facet['count'] }})</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </aside>

                    {{-- Product grid (SSR) --}}
                    <div class="col-lg-9">
                        <div class="tf-grid-layout wrapper-shop tf-col-2 lg-col-3" data-grid>
                            @foreach ($products as $product)
                                <x-theme::product-card :product="$product" />
                            @endforeach
                        </div>
                        @if ($products->isEmpty())
                            <p class="text-center" data-empty>No products found.</p>
                        @endif

                        {{-- Pagination (SSR, real links — crawlable) --}}
                        <ul class="wg-pagination justify-content-center mt_20" data-pagination @if(($meta['last_page'] ?? 1) <= 1) style="display:none" @endif>
                            @for ($p = 1; $p <= ($meta['last_page'] ?? 1); $p++)
                                <li @class(['active' => $p === ($meta['page'] ?? 1)])>
                                    <a href="?{{ http_build_query(array_merge(request()->query(), ['page' => $p])) }}" data-page="{{ $p }}">{{ $p }}</a>
                                </li>
                            @endfor
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
