@extends('theme::layouts.app')

@section('title', 'Order confirmed')

@section('content')
    <div class="page-title">
        <div class="container">
            <h3 class="heading text-center">Thank you!</h3>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            <div class="tf-page-checkout text-center">
                <p>Your order <strong>{{ $order->reference }}</strong> has been placed.</p>
                <p>Status: {{ $order->status }}</p>
                <p>Total: {{ $order->total?->formatted() }}</p>

                <ul class="list-order-confirm mt_20 mb_20">
                    @foreach ($order->lines as $line)
                        <li>{{ $line->description }} × {{ $line->quantity }} — {{ $line->sub_total?->formatted() }}</li>
                    @endforeach
                </ul>

                <a href="{{ route('storefront.home') }}" class="tf-btn btn-fill"><span class="text">Continue shopping</span></a>
            </div>
        </div>
    </section>
@endsection
