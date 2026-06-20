@extends('theme::layouts.app')

@section('title', 'Checkout — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-4">Checkout</h1>

    {{-- Checkout island. Cart/totals are session content (fetched on mount);
         countries are embedded so the address form has no extra round-trip.
         confirmation_base lets the island redirect after placing the order. --}}
    @php
        $islandState = [
            'countries' => $countries,
            'confirmationBase' => url('/checkout/confirmation'),
            'vnpayEnabled' => $vnpayEnabled,
        ];
    @endphp
    <div data-vue="checkout-page">
        <script type="application/json" data-island-state>@json($islandState)</script>

        <noscript>
            <p class="text-muted">Checkout requires JavaScript. Please enable it to continue.</p>
        </noscript>
        <div class="text-center text-muted py-5">Loading checkout…</div>
    </div>
</div>
@endsection
