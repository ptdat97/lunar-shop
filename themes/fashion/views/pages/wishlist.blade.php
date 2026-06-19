@extends('theme::layouts.app')

@section('title', 'Wishlist — '.config('app.name'))

@section('content')
<div class="container py-4" data-wishlist-page>
    <h1 class="h3 mb-4">Wishlist</h1>

    @if(!$authed)
        <div class="text-center text-muted py-5">
            <p class="mb-3">Please sign in to view your wishlist.</p>
            <a href="{{ route('storefront.login') }}" class="btn btn-dark">Sign in</a>
        </div>
    @elseif($products->isEmpty())
        <div class="text-center text-muted py-5" data-wishlist-empty>
            <p class="mb-3">Your wishlist is empty.</p>
            <a href="{{ route('storefront.search') }}" class="btn btn-dark">Browse products</a>
        </div>
    @else
        {{-- SSR grid (crawlable). JS only enhances remove/toggle. --}}
        <div class="row g-4" data-grid>
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3" data-wishlist-item="{{ $product->id }}">
                    @include('theme::components.product-card', ['product' => $product])
                    <button class="btn btn-outline-danger btn-sm w-100 mt-2"
                            data-wishlist-toggle data-product-id="{{ $product->id }}">
                        Remove
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
