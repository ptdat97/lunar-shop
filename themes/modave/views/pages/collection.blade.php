@extends('theme::layouts.app')

@php
    $name = $collection->translateAttribute('name');
    $description = $collection->translateAttribute('description');
    $currentSort = request('sort', 'best-selling');
    $sortLabels = [
        'best-selling' => 'Best selling',
        'a-z' => 'Alphabetically, A-Z',
        'z-a' => 'Alphabetically, Z-A',
        'price-low-high' => 'Price, low to high',
        'price-high-low' => 'Price, high to low',
    ];
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

            {{-- Interactive shop: sidebar filters + sort + grid (fetches /api/v1/search) --}}
            <div
                data-vue="collection-shop"
                data-scope="{{ $collection->defaultUrl?->slug }}"
                data-initial-sort="{{ $currentSort }}"
            >
                {{-- SSR fallback grid (SEO + no-JS): replaced by the island on mount --}}
                <noscript>
                    <div class="tf-grid-layout wrapper-shop tf-col-4">
                        @foreach ($products as $product)
                            <x-theme::product-card :product="$product" />
                        @endforeach
                    </div>
                </noscript>
            </div>
        </div>
    </section>
@endsection
