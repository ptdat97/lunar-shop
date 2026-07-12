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
                {{-- Product lines only. Shipping is a separate order line in
                     Lunar; it's shown in the totals breakdown below, not here,
                     so it isn't counted twice. --}}
                <table class="table table-sm align-middle mb-3">
                    <tbody>
                        @foreach($order->productLines as $line)
                            <tr>
                                <td>{{ $line->description }}</td>
                                <td class="text-center">× {{ $line->quantity }}</td>
                                <td class="text-end">{{ $line->sub_total?->formatted() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Full breakdown so the total reconciles with the lines: the
                     discount (e.g. flash sale) is otherwise invisible and the
                     total looks wrong against the listed prices. --}}
                {{-- Lunar's Order only exposes a camelCase accessor for `total`;
                     the other totals are Price-cast columns read snake_case
                     ($order->sub_total, ->shipping_total, …). Using camelCase
                     here returns null and the values render blank. --}}
                <dl class="mb-0 border-top pt-2">
                    <div class="d-flex justify-content-between mb-1">
                        <dt class="fw-normal text-muted">{{ __('storefront.cart.subtotal') }}</dt>
                        <dd class="mb-0">{{ $order->sub_total?->formatted() }}</dd>
                    </div>
                    @if($order->discount_total?->value)
                        <div class="d-flex justify-content-between mb-1 text-success">
                            <dt class="fw-normal">{{ __('storefront.cart.discount') }}</dt>
                            <dd class="mb-0">−{{ $order->discount_total?->formatted() }}</dd>
                        </div>
                    @endif
                    <div class="d-flex justify-content-between mb-1">
                        <dt class="fw-normal text-muted">{{ __('storefront.cart.shipping') }}</dt>
                        <dd class="mb-0">{{ $order->shipping_total?->formatted() }}</dd>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <dt class="fw-normal text-muted">{{ __('storefront.cart.tax') }}</dt>
                        <dd class="mb-0">{{ $order->tax_total?->formatted() }}</dd>
                    </div>
                    <div class="d-flex justify-content-between fw-bold border-top pt-2">
                        <dt>{{ __('storefront.checkout.grand_total') }}</dt>
                        <dd class="mb-0">{{ $order->total?->formatted() }}</dd>
                    </div>
                </dl>
            </div>
            <div class="text-center mt-4">
                <a href="{{ route('storefront.home') }}" class="btn btn-dark">{{ __('storefront.common.continue_shopping') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
