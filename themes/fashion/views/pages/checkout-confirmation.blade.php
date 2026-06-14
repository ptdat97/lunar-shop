@extends('theme::layouts.app')

@section('title', 'Order confirmed')

@section('content')
    <section class="confirmation">
        <h1>Thank you!</h1>
        <p>Your order <strong>{{ $order->reference }}</strong> has been placed.</p>
        <p>Status: {{ $order->status }}</p>
        <p>Total: {{ $order->total?->formatted() }}</p>

        <ul class="confirmation__lines">
            @foreach ($order->lines as $line)
                <li>{{ $line->description }} × {{ $line->quantity }} — {{ $line->sub_total?->formatted() }}</li>
            @endforeach
        </ul>

        <a href="{{ route('storefront.home') }}" class="btn">Continue shopping</a>
    </section>
@endsection
