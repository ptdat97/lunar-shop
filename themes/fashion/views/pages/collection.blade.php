@extends('theme::layouts.app')
@section('body_class', 'page-collection')

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

@push('head')
@php
    // ItemList of the SSR-rendered products (crawlable list) + breadcrumb trail.
    $collName = $collection->translateAttribute('name');
    $itemListLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $collName,
        'itemListElement' => collect($products)->values()
            ->map(fn ($p, $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $p->defaultUrl?->slug ? route('storefront.product', $p->defaultUrl->slug) : null,
                'name' => $p->translateAttribute('name'),
            ]))
            ->filter(fn ($el) => isset($el['url']))
            ->values()->all(),
    ];
    $collBreadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('storefront.home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $collName, 'item' => url()->current()],
        ],
    ];
@endphp
<script type="application/ld+json">@json($itemListLd, JSON_UNESCAPED_SLASHES)</script>
<script type="application/ld+json">@json($collBreadcrumbLd, JSON_UNESCAPED_SLASHES)</script>
@endpush
