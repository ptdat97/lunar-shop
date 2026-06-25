@extends('theme::layouts.app')

@section('title', __('storefront.cart.title').' — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-4" data-cart-page>
    <h1 class="h3 mb-4">{{ __('storefront.cart.title') }}</h1>

    <div class="text-center text-muted py-5" data-cart-loading>…</div>

    <div class="row g-4" data-cart-content hidden>
        <div class="col-12 col-lg-8">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr class="small text-uppercase text-muted">
                            <th colspan="2">{{ __('storefront.cart.product') }}</th>
                            <th>{{ __('storefront.common.price') }}</th>
                            <th>{{ __('storefront.cart.qty') }}</th>
                            <th class="text-end">{{ __('storefront.cart.subtotal') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-cart-lines></tbody>
                </table>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="border rounded p-3">
                <h2 class="h6 text-uppercase">{{ __('storefront.cart.order_summary') }}</h2>

                {{-- Coupon --}}
                <form class="mb-3" data-coupon-form>
                    <label class="form-label small">{{ __('storefront.cart.coupon_code') }}</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control" name="code" placeholder="{{ __('storefront.cart.coupon_code') }}" data-coupon-input>
                        <button class="btn btn-outline-dark" type="submit">{{ __('storefront.common.apply') }}</button>
                    </div>
                    <div class="small mt-1" data-coupon-status></div>
                </form>

                {{-- Applied promotions — one labelled row per discount,
                     rendered by enhance/cart-page.js. --}}
                <div class="mb-2" data-cart-discounts></div>

                <dl class="row small mb-0">
                    <dt class="col-7 fw-normal">{{ __('storefront.cart.subtotal') }}</dt><dd class="col-5 text-end" data-sum-subtotal>—</dd>
                    <dt class="col-7 fw-normal">{{ __('storefront.cart.discount') }}</dt><dd class="col-5 text-end" data-sum-discount>—</dd>
                    <dt class="col-7 fw-normal">{{ __('storefront.cart.tax') }}</dt><dd class="col-5 text-end" data-sum-tax>—</dd>
                    <dt class="col-7 fw-bold border-top pt-2">{{ __('storefront.cart.total') }}</dt>
                    <dd class="col-5 text-end fw-bold border-top pt-2" data-sum-total>—</dd>
                </dl>

                <a href="{{ route('storefront.checkout') }}" class="btn btn-dark w-100 mt-3">{{ __('storefront.cart.checkout') }}</a>
            </div>
        </div>
    </div>

    <div class="text-center text-muted py-5" data-cart-empty hidden>
        <p class="mb-3">{{ __('storefront.cart.empty') }}</p>
        <a href="{{ route('storefront.search') }}" class="btn btn-dark">{{ __('storefront.common.continue_shopping') }}</a>
    </div>

    {{-- "You may also like" — loaded from /api/v1/cart/recommendations
         (session-scoped, not SEO content). Hidden until it has items. Same
         ProductResource shape → rendered by _card.js, matching the drawer. --}}
    <section class="mt-5" data-cart-recommendations hidden>
        <h2 class="h5 text-uppercase mb-3">{{ __('storefront.cart.you_may_also_like') }}</h2>
        <div class="row g-4" data-cart-recommendations-grid></div>
    </section>
</div>
@endsection
