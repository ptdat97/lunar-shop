@extends('theme::layouts.app')

@php
    $products = $products ?? collect();
    $meta = $state['meta'] ?? ['total' => $products->count()];
@endphp

@section('title', 'Search')

@section('content')
    <div class="page-title">
        <div class="container-full">
            <div class="row">
                <div class="col-12">
                    <h3 class="heading text-center">Search</h3>
                </div>
            </div>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            {{--
                SSR-first search (Blade SSR + vanilla JS enhancement).
                - SSR shell: the search form + result grid render server-side
                  (crawlable, works with JS off via a real GET form).
                - JS enhancement: enhance/search-results.js re-queries the API as the
                  user edits the term and re-renders the grid without a full reload.
            --}}
            <div data-search-results data-query="{{ $query }}">
                {{-- SSR search form (GET — works without JS) --}}
                <form method="get" action="{{ route('storefront.search') }}" class="d-flex gap-2 mb_20" data-search-form>
                    <input type="search" name="q" class="form-control" value="{{ $query }}" placeholder="Search products…" data-search-input>
                    <button type="submit" class="tf-btn btn-fill"><span class="text">Search</span></button>
                </form>

                @php
                    $hasQuery = $query !== '';
                    $isEmpty = $hasQuery && $products->isEmpty();
                    $forTerm = $hasQuery ? ' for “' . $query . '”' : '';
                @endphp

                <p class="mb_20 text-secondary" data-result-count style="{{ $hasQuery ? '' : 'display:none' }}">
                    {{ $meta['total'] }} results{{ $forTerm }}
                </p>

                <p class="text-center" data-empty style="{{ $isEmpty ? '' : 'display:none' }}">
                    No results{{ $forTerm }}.
                </p>

                {{-- SSR result grid --}}
                <div class="tf-grid-layout tf-col-2 md-col-3 lg-col-4" data-grid style="{{ $products->isEmpty() ? 'display:none' : '' }}">
                    @foreach ($products as $product)
                        <x-theme::product-card :product="$product" />
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endsection
