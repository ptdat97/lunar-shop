@extends('theme::layouts.app')

@section('title', 'Cart — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-4" data-cart-page>
    <h1 class="h3 mb-4">Your cart</h1>

    <div class="text-center text-muted py-5" data-cart-loading>Loading your cart…</div>

    <div class="row g-4" data-cart-content hidden>
        <div class="col-12 col-lg-8">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="small text-uppercase text-muted">
                            <th colspan="2">Product</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-cart-lines></tbody>
                </table>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="border rounded p-3">
                <h2 class="h6 text-uppercase">Order summary</h2>

                {{-- Coupon --}}
                <form class="mb-3" data-coupon-form>
                    <label class="form-label small">Coupon code</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control" name="code" placeholder="Enter code" data-coupon-input>
                        <button class="btn btn-outline-dark" type="submit">Apply</button>
                    </div>
                    <div class="small mt-1" data-coupon-status></div>
                </form>

                <dl class="row small mb-0">
                    <dt class="col-7 fw-normal">Subtotal</dt><dd class="col-5 text-end" data-sum-subtotal>—</dd>
                    <dt class="col-7 fw-normal">Discount</dt><dd class="col-5 text-end" data-sum-discount>—</dd>
                    <dt class="col-7 fw-normal">Tax</dt><dd class="col-5 text-end" data-sum-tax>—</dd>
                    <dt class="col-7 fw-bold border-top pt-2">Total</dt>
                    <dd class="col-5 text-end fw-bold border-top pt-2" data-sum-total>—</dd>
                </dl>

                <a href="{{ route('storefront.checkout') }}" class="btn btn-dark w-100 mt-3">Checkout</a>
            </div>
        </div>
    </div>

    <div class="text-center text-muted py-5" data-cart-empty hidden>
        <p class="mb-3">Your cart is empty.</p>
        <a href="{{ route('storefront.search') }}" class="btn btn-dark">Continue shopping</a>
    </div>
</div>
@endsection
