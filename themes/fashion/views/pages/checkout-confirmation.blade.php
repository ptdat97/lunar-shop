@extends('theme::layouts.app')

@section('title', 'Order confirmed — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-5">
    <div class="text-center mb-4">
        <h1 class="h3">Thank you for your order!</h1>
        <p class="text-muted">Your order <strong>{{ $order->reference }}</strong> has been placed.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>Reference</span><span>{{ $order->reference }}</span>
                </div>
                <table class="table table-sm align-middle mb-3">
                    <tbody>
                        @foreach($order->lines as $line)
                            <tr>
                                <td>{{ $line->description }}</td>
                                <td class="text-center">× {{ $line->quantity }}</td>
                                <td class="text-end">{{ $line->sub_total?->formatted() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="d-flex justify-content-between fw-bold border-top pt-2">
                    <span>Total</span><span>{{ $order->total?->formatted() }}</span>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('storefront.home') }}" class="btn btn-dark">Continue shopping</a>
            </div>
        </div>
    </div>
</div>
@endsection
