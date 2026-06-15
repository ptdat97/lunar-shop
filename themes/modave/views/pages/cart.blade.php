@extends('theme::layouts.app')

@section('title', 'Shopping Cart')

@section('content')
    <div class="page-title">
        <div class="container">
            <h3 class="heading text-center">Shopping Cart</h3>
            <ul class="breadcrumbs d-flex align-items-center justify-content-center">
                <li><a class="link" href="{{ route('storefront.home') }}">Homepage</a></li>
                <li><i class="icon-arrRight"></i></li>
                <li><a class="link" href="/search">Shop</a></li>
                <li><i class="icon-arrRight"></i></li>
                <li>Shopping Cart</li>
            </ul>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            {{-- Vue island: cart table + order summary (fetches /api/v1/cart) --}}
            <div data-vue="cart-page"></div>
        </div>
    </section>
@endsection
