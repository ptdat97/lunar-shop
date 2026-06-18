@extends('theme::layouts.app')

@section('title', 'Wishlist')

@section('content')
    <div class="page-title">
        <div class="container-full">
            <div class="row"><div class="col-12">
                <h3 class="heading text-center">Wishlist</h3>
            </div></div>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            {{-- SSR wishlist grid; vanilla JS enhances the heart toggle. --}}
            <div data-wishlist-page>
                @if (! $authed)
                    <div class="text-center">
                        <p>Please log in to view your wishlist.</p>
                        <a href="{{ route('storefront.login') }}" class="tf-btn btn-fill"><span class="text">Log in</span></a>
                    </div>
                @elseif ($products->isEmpty())
                    <p class="text-center">Your wishlist is empty.</p>
                @else
                    <div class="tf-grid-layout tf-col-2 md-col-3 lg-col-4">
                        @foreach ($products as $product)
                            <x-theme::product-card :product="$product" />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
