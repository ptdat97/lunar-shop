@extends('theme::layouts.app')

@php
    $name = $product->translateAttribute('name');
    $description = $product->translateAttribute('description');
    // Hydration payload for the variant island (Bước 4) — same ProductResource
    // shape as GET /api/v1/products/{slug}. SSR below renders without it.
    $state = (new \Modules\Product\Http\Resources\ProductResource(
        $product->loadMissing(['variants.values.option'])
    ))->resolve();

    $media = $product->media ?? collect();
    $firstVariant = $product->variants->first();
@endphp

@section('title', $name.' — '.config('app.name'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $description), 155))

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">Home</a></li>
            @if($collection = $product->collections->first())
                <li class="breadcrumb-item">
                    <a href="{{ $collection->defaultUrl?->slug ? route('storefront.collection', $collection->defaultUrl->slug) : '#' }}"
                       class="text-decoration-none">{{ $collection->translateAttribute('name') }}</a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $name }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        {{-- Gallery --}}
        <div class="col-12 col-lg-7">
            <div class="row g-2">
                @forelse($media as $image)
                    @php
                        try { $url = $image->hasGeneratedConversion('large') ? $image->getUrl('large') : $image->getUrl(); }
                        catch (\Throwable $e) { $url = null; }
                    @endphp
                    @if($url)
                        <div class="{{ $loop->first ? 'col-12' : 'col-6' }}">
                            <img src="{{ $url }}" alt="{{ $name }}" class="img-fluid rounded w-100"
                                 loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                        </div>
                    @endif
                @empty
                    <div class="col-12 ratio ratio-4x3 bg-light rounded d-flex align-items-center justify-content-center text-muted">
                        No image
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Purchase panel --}}
        <div class="col-12 col-lg-5">
            @if($brand = $product->brand?->name)
                <div class="text-muted text-uppercase small">{{ $brand }}</div>
            @endif
            <h1 class="h3">{{ $name }}</h1>

            @include('theme::components.price', ['product' => $product])

            {{-- Variant picker.
                 SSR renders the variant list as a real fallback; the Vue island
                 (Bước 4) mounts here and hydrates from data-island-state. --}}
            <div class="my-4" data-vue="product-purchase" data-slug="{{ $slug }}">
                <script type="application/json" data-island-state>@json($state)</script>

                @if($product->variants->count() > 1)
                    <label class="form-label small text-uppercase">Variant</label>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach($product->variants as $variant)
                            <button type="button" class="btn btn-outline-dark btn-sm"
                                    data-variant="{{ $variant->id }}"
                                    @disabled($variant->stock <= 0)>
                                {{ $variant->sku }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="#" data-add-to-cart>
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $firstVariant?->id }}">
                    <button class="btn btn-dark btn-lg w-100" type="submit"
                            @disabled(!$firstVariant || $firstVariant->stock <= 0)>
                        {{ $firstVariant && $firstVariant->stock > 0 ? 'Add to cart' : 'Out of stock' }}
                    </button>
                </form>
            </div>

            @if($description)
                <div class="mt-4">
                    <h2 class="h6 text-uppercase">Description</h2>
                    <div class="text-muted">{!! $description !!}</div>
                </div>
            @endif

            @include('theme::partials.size-chart', ['sizeChart' => $sizeChart, 'slug' => $slug])
        </div>
    </div>

    {{-- Related / You may also like --}}
    @if($related->isNotEmpty())
        <section class="mt-5">
            <h2 class="h4 mb-3">You may also like</h2>
            <div class="row g-4">
                @foreach($related as $item)
                    <div class="col-6 col-md-4 col-lg-3">
                        @include('theme::components.product-card', ['product' => $item])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
