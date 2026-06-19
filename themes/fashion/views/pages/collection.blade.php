@extends('theme::layouts.app')

@section('title', $collection->translateAttribute('name').' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $collection->translateAttribute('description')), 155))
{{-- Canonical drops filter/sort/page query so faceted variants don't compete. --}}
@section('canonical', url()->current())

@section('content')
    <div class="container pt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $collection->translateAttribute('name') }}</li>
            </ol>
        </nav>
        <h1 class="h3 mb-0">{{ $collection->translateAttribute('name') }}</h1>
        @if($desc = $collection->translateAttribute('description'))
            <p class="text-muted">{{ $desc }}</p>
        @endif
    </div>

    @include('theme::partials.shop', [
        'shopType' => 'collection',
        'scope' => $collection->defaultUrl?->slug,
        'currentSort' => $currentSort ?? request('sort'),
    ])
@endsection
