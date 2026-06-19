{{-- Shared SSR-first catalog grid (collection + search).
     Renders REAL HTML (crawlable, no-JS friendly) and embeds the same
     {data,facets,meta} payload the /api/v1/search island/enhancer hydrate from.

     Expects:
       $products    Eloquent collection (SSR grid)
       $facets      ['size'=>[{value,count}], 'color'=>[...]]  (may be empty)
       $state       JSON payload (same shape as GET /api/v1/search)
       $shopType    'collection' | 'search'  (data-shop hook for the enhancer)
       $scope       collection slug for scoped fetches (collection page) or null
       $currentSort selected sort key
--}}
@php
    $scope = $scope ?? null;
    // Facets may be passed directly (collection) or read from the embedded state (search).
    $facets = $facets ?? data_get($state, 'facets', []);
    $currentSort = $currentSort ?? request('sort');
    $activeFilters = (array) request('filters', []);
    $sortOptions = [
        'newest' => 'Newest',
        'price-low-high' => 'Price: low to high',
        'price-high-low' => 'Price: high to low',
        'a-z' => 'Name: A–Z',
        'z-a' => 'Name: Z–A',
    ];
    $total = data_get($state, 'meta.total', $products->count());
@endphp

<div class="container my-4" data-shop="{{ $shopType }}" @if($scope) data-scope="{{ $scope }}" @endif>
    {{-- Hydration payload: one contract for SSR + enhancer (no fetch on load). --}}
    <script type="application/json" data-island-state>@json($state)</script>

    <div class="row">
        {{-- Facet sidebar — real GET form so filtering works without JS. --}}
        <aside class="col-12 col-lg-3 mb-4">
            <form method="GET" data-facet-form>
                @if($shopType === 'search' && request('q'))
                    <input type="hidden" name="q" value="{{ request('q') }}">
                @endif
                <div data-facets>
                    @foreach($facets as $key => $buckets)
                        @continue(empty($buckets))
                        <div class="mb-4">
                            <h6 class="text-uppercase small mb-2">{{ $key }}</h6>
                            @foreach($buckets as $bucket)
                                @php $id = 'f-'.$key.'-'.\Illuminate\Support\Str::slug($bucket['value']); @endphp
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="{{ $id }}"
                                           data-facet="{{ $key }}"
                                           name="filters[{{ $key }}][]" value="{{ $bucket['value'] }}"
                                           @checked(in_array($bucket['value'], (array) ($activeFilters[$key] ?? [])))>
                                    <label class="form-check-label d-flex justify-content-between" for="{{ $id }}">
                                        <span>{{ $bucket['value'] }}</span>
                                        <span class="text-muted small">{{ $bucket['count'] }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <noscript><button class="btn btn-dark btn-sm w-100">Apply</button></noscript>
            </form>
        </aside>

        <div class="col-12 col-lg-9">
            {{-- Toolbar --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small"><span data-result-count>{{ $total }}</span> products</span>
                <form method="GET" class="d-flex align-items-center gap-2">
                    @if($shopType === 'search' && request('q'))
                        <input type="hidden" name="q" value="{{ request('q') }}">
                    @endif
                    <label class="small text-muted mb-0" for="sort">Sort</label>
                    <select class="form-select form-select-sm w-auto" id="sort" name="sort" data-sort>
                        @foreach($sortOptions as $value => $label)
                            <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-dark btn-sm">Go</button></noscript>
                </form>
            </div>

            {{-- SSR grid --}}
            @if($products->isEmpty())
                <div class="text-center text-muted py-5">No products found.</div>
            @else
                <div class="row g-4" data-grid>
                    @foreach($products as $product)
                        <div class="col-6 col-md-4 col-lg-3">
                            @include('theme::components.product-card', ['product' => $product])
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- SSR pagination (real GET links; enhancer swaps to in-place) --}}
            @php
                $page = (int) data_get($state, 'meta.page', 1);
                $lastPage = (int) data_get($state, 'meta.last_page', 1);
            @endphp
            <nav class="mt-4" data-pagination>
                @if($lastPage > 1)
                    <ul class="pagination justify-content-center mb-0">
                        @for($p = 1; $p <= $lastPage; $p++)
                            <li class="page-item {{ $p === $page ? 'active' : '' }}">
                                <a class="page-link" data-page="{{ $p }}"
                                   href="{{ request()->fullUrlWithQuery(['page' => $p]) }}">{{ $p }}</a>
                            </li>
                        @endfor
                    </ul>
                @endif
            </nav>
        </div>
    </div>
</div>
