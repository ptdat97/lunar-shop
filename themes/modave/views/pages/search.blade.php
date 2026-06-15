@extends('theme::layouts.app')

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
            {{-- Vue island: live search results from /api/v1/search --}}
            <div data-vue="search-results" data-query="{{ $query }}"></div>
        </div>
    </section>
@endsection
