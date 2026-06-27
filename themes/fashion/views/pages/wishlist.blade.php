@extends('theme::layouts.app')
@section('body_class', 'page-wishlist')

@section('title', __('storefront.wishlist.title').' — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-4" data-wishlist-page>
    <h1 class="h3 mb-4">{{ __('storefront.wishlist.title') }}</h1>

    @if(!$authed)
        <div class="text-center text-muted py-5">
            <p class="mb-3">{{ __('storefront.wishlist.sign_in_required') }}</p>
            <a href="{{ route('storefront.login') }}" class="btn btn-dark">{{ __('storefront.auth.sign_in') }}</a>
        </div>
    @elseif($products->isEmpty())
        <div class="text-center text-muted py-5" data-wishlist-empty>
            <p class="mb-3">{{ __('storefront.wishlist.empty') }}</p>
            <a href="{{ route('storefront.search') }}" class="btn btn-dark">{{ __('storefront.common.browse_products') }}</a>
        </div>
    @else
        {{-- SSR grid (crawlable). JS only enhances remove/toggle. --}}
        <div class="row g-4" data-grid>
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3" data-wishlist-item="{{ $product->id }}">
                    @include('theme::components.product-card', ['product' => $product])
                    <button class="btn btn-outline-danger btn-sm w-100 mt-2"
                            data-wishlist-toggle data-product-id="{{ $product->id }}">
                        {{ __('storefront.common.remove') }}
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
