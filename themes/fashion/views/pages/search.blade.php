@extends('theme::layouts.app')

@section('title', ($query ? 'Search: '.$query : 'Search').' — '.config('app.name'))

@section('content')
    <div class="container pt-4">
        <form method="GET" action="{{ route('storefront.search') }}" class="mb-2">
            <div class="input-group input-group-lg">
                <input type="search" name="q" value="{{ $query }}" class="form-control"
                       placeholder="Search products…" autofocus>
                <button class="btn btn-dark" type="submit"><i class="bi bi-search"></i></button>
            </div>
        </form>
        @if($query)
            <h1 class="h5 text-muted">Results for “{{ $query }}”</h1>
        @endif
    </div>

    @include('theme::partials.shop', [
        'shopType' => 'search',
        'scope' => null,
        'facets' => null,
        'currentSort' => request('sort'),
    ])
@endsection
