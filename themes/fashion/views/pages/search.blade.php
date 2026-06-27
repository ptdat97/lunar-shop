@extends('theme::layouts.app')
@section('body_class', 'page-search')

@section('title', ($query ? __('storefront.search.title').': '.$query : __('storefront.search.title')).' — '.config('app.name'))
{{-- Search results are not index-worthy (thin/duplicate); keep them out. --}}
@section('robots', 'noindex, follow')

@section('content')
    <div class="container pt-4">
        <form method="GET" action="{{ route('storefront.search') }}" class="mb-2">
            <div class="input-group input-group-lg">
                <input type="search" name="q" value="{{ $query }}" class="form-control"
                       placeholder="{{ __('storefront.search.placeholder') }}" autofocus>
                <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
        @if($query)
            <h1 class="h5 text-muted">{{ __('storefront.search.results_for', ['query' => $query]) }}</h1>
        @endif
    </div>

    @include('theme::partials.shop', [
        'shopType' => 'search',
        'scope' => null,
        'facets' => null,
        'currentSort' => request('sort'),
    ])
@endsection
