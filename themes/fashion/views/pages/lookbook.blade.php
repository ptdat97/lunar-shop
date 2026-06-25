@extends('theme::layouts.app')

@section('title', $lookbook->title.' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $lookbook->description), 155))
@section('canonical', url()->current())

@section('content')
    @php
        // $fileUrl resolver injected by the FileManager view composer (§7).
        $cover = $fileUrl($lookbook->cover_image, 'large');
        $images = $lookbook->images;
        $products = $lookbook->items->map->product->filter()->values();
    @endphp

    {{-- Hero / cover --}}
    <header class="lookbook-hero position-relative mb-4">
        @if($cover)
            <div class="lookbook-hero__media">
                <img src="{{ $cover }}" alt="{{ $lookbook->title }}" class="w-100" style="object-fit:cover" loading="eager">
            </div>
        @endif
        <div class="container py-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">{{ __('storefront.nav.home') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('storefront.lookbooks') }}" class="text-decoration-none">{{ __('storefront.lookbook.title') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $lookbook->title }}</li>
                </ol>
            </nav>
            <h1 class="h3 mb-2">{{ $lookbook->title }}</h1>
            @if($lookbook->description)
                <p class="text-muted mb-0">{{ $lookbook->description }}</p>
            @endif
        </div>
    </header>

    {{-- Photo gallery --}}
    @if($images->isNotEmpty())
        <section class="container mb-5">
            <div class="lookbook-gallery">
                @foreach($images as $image)
                    @php $src = $fileUrl($image->image, 'large'); @endphp
                    @if($src)
                        <figure class="lookbook-gallery__item mb-0">
                            <img src="{{ $src }}" alt="{{ $image->caption ?? $lookbook->title }}"
                                 class="w-100 rounded" loading="lazy">
                            @if($image->caption)
                                <figcaption class="small text-muted mt-1">{{ $image->caption }}</figcaption>
                            @endif
                        </figure>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Products at the bottom --}}
    @if($products->isNotEmpty())
        <section class="container my-5">
            <h2 class="h4 mb-4">{{ __('storefront.lookbook.shop_this') }}</h2>
            <div class="row g-4">
                @foreach($products as $product)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('theme::components.product-card', ['product' => $product])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
