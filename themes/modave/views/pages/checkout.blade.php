@extends('theme::layouts.app')

@section('title', 'Checkout')

@section('content')
    <div class="page-title">
        <div class="container">
            <h3 class="heading text-center">Check Out</h3>
            <ul class="breadcrumbs d-flex align-items-center justify-content-center">
                <li><a class="link" href="{{ route('storefront.home') }}">Homepage</a></li>
                <li><i class="icon-arrRight"></i></li>
                <li><a class="link" href="{{ route('storefront.cart') }}">Cart</a></li>
                <li><i class="icon-arrRight"></i></li>
                <li>Check Out</li>
            </ul>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            {{-- Country list as JSON (avoids attribute escaping). --}}
            <script type="application/json" data-checkout-countries>
                @json($countries ?? \Lunar\Models\Country::orderBy('name')->get(['id', 'name']))
            </script>

            @guest
                <div class="checkout-login-note tf-page-checkout mb_16">
                    <div class="wrap">
                        <div class="title-login d-flex align-items-center gap-2">
                            <p class="mb-0">Already have an account?</p>
                            <a href="{{ route('storefront.login') }}" class="text-button link">Login here</a>
                        </div>
                    </div>
                </div>
            @endguest

            {{-- Vue island: checkout (address → shipping → payment → order) --}}
            <div data-vue="checkout-page" data-confirm-url="{{ url('/checkout/confirmation') }}"></div>
        </div>
    </section>
@endsection
