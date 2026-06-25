@extends('theme::layouts.app')

@section('title', $discount->name.' — '.config('app.name'))
@section('meta_description', $description)

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('storefront.home') }}" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('storefront.promotions') }}" class="text-decoration-none">Promotions</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $discount->name }}</li>
        </ol>
    </nav>

    {{-- Promotion header (banner). Flash sales get a live countdown via
         enhance/flash-sale.js (reads data-ends-at). --}}
    <header class="promotion-header rounded p-4 mb-4 text-white {{ $isFlashSale ? 'bg-danger' : 'bg-dark' }}"
            @if($discount->ends_at) data-flash-sale data-ends-at="{{ $discount->ends_at->toIso8601String() }}" @endif>
        <h1 class="h3 mb-1">
            @if($isFlashSale)<i class="bi bi-lightning-charge-fill"></i>@endif
            {{ $discount->name }}
        </h1>
        <p class="mb-0 opacity-75">{{ $description }}</p>
        @if($discount->ends_at)
            <div class="mt-2 d-inline-flex align-items-center gap-2 small">
                <span class="opacity-75">Ends in</span>
                <span class="badge bg-light text-dark font-monospace" data-flash-countdown>—</span>
            </div>
        @endif
    </header>

    @if($products->isNotEmpty())
        <div class="row g-3">
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('theme::components.product-card', ['product' => $product])
                </div>
            @endforeach
        </div>
    @else
        <p class="text-muted py-5 text-center">No products in this promotion yet.</p>
    @endif
</div>
@endsection
