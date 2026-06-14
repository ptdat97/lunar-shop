@extends('theme::layouts.app')

@section('title', 'Search')

@section('content')
    <section class="search-page">
        <h1>Search</h1>

        {{-- Vue island: live results from /api/v1/search --}}
        <div data-vue="search-results" data-query="{{ $query }}"></div>
    </section>
@endsection
