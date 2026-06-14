@extends('theme::layouts.app')

@section('title', 'Checkout')

@section('content')
    <section class="checkout">
        <h1>Checkout</h1>

        {{-- Country list embedded as JSON (avoids HTML-attribute escaping issues). --}}
        <script type="application/json" data-checkout-countries>
            @json($countries ?? \Lunar\Models\Country::orderBy('name')->get(['id', 'name']))
        </script>

        {{-- Vue island drives address → shipping → payment via /api/v1/checkout --}}
        <div data-vue="checkout-flow"
             data-confirm-url="{{ url('/checkout/confirmation') }}"></div>
    </section>
@endsection
