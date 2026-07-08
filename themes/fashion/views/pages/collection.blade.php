@extends('theme::layouts.app')
@section('body_class', 'page-collection')

@section('title', $collection->translateAttribute('name').' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $collection->translateAttribute('description')), 155))
{{-- Canonical drops filter/sort/page query so faceted variants don't compete. --}}
@section('canonical', url()->current())
@if (! empty($ogImage))
    @section('og_image', $ogImage)
@endif

@section('content')
    @php $desc = $collection->translateAttribute('description'); @endphp

    @if(!empty($bannerImage))
        {{-- Collection banner: the collection's own image with the name +
             description overlaid. Falls back to the plain header below when the
             collection has no image. --}}
        <section class="collection-banner" style="background-image:url('{{ $bannerImage }}');">
            <div class="collection-banner__scrim"></div>
            <div class="container collection-banner__inner">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small collection-banner__crumbs">
                        <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">{{ __('storefront.static.home_breadcrumb') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ $collection->translateAttribute('name') }}</li>
                    </ol>
                </nav>
                <h1 class="collection-banner__title">{{ $collection->translateAttribute('name') }}</h1>
                @if($desc)
                    <p class="collection-banner__desc">{{ $desc }}</p>
                @endif
            </div>
        </section>
    @else
        <div class="container pt-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">{{ __('storefront.static.home_breadcrumb') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $collection->translateAttribute('name') }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0">{{ $collection->translateAttribute('name') }}</h1>
            @if($desc)
                <p class="text-muted">{{ $desc }}</p>
            @endif
        </div>
    @endif

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
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('storefront.static.home_breadcrumb'), 'item' => route('storefront.home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $collName, 'item' => url()->current()],
        ],
    ];
@endphp
<script type="application/ld+json">@json($itemListLd, JSON_UNESCAPED_SLASHES)</script>
<script type="application/ld+json">@json($collBreadcrumbLd, JSON_UNESCAPED_SLASHES)</script>
@endpush
