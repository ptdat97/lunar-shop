@extends('theme::layouts.app')
@section('body_class', 'page-checkout-confirmation')

@section('title', __('storefront.checkout.thank_you').' — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-5">
    <div class="text-center mb-4">
        <h1 class="h3">{{ __('storefront.checkout.thank_you') }}</h1>
        <p class="text-muted">{!! __('storefront.checkout.order_reference', ['reference' => '<strong>'.e($order->reference).'</strong>']) !!}</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>{{ __('storefront.cart.order_summary') }}</span><span>{{ $order->reference }}</span>
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
                    <span>{{ __('storefront.cart.total') }}</span><span>{{ $order->total?->formatted() }}</span>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('storefront.home') }}" class="btn btn-dark">{{ __('storefront.common.continue_shopping') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
