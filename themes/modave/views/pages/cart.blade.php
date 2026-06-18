@extends('theme::layouts.app')

@section('title', 'Shopping Cart')

@section('content')
    <div class="page-title">
        <div class="container">
            <h3 class="heading text-center">Shopping Cart</h3>
            <ul class="breadcrumbs d-flex align-items-center justify-content-center">
                <li><a class="link" href="{{ route('storefront.home') }}">Homepage</a></li>
                <li><i class="icon-arrRight"></i></li>
                <li><a class="link" href="/search">Shop</a></li>
                <li><i class="icon-arrRight"></i></li>
                <li>Shopping Cart</li>
            </ul>
        </div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            {{-- Cart page (vanilla). enhance/cart-page.js fills the table + summary
                 from /api/v1/cart. Cart is per-session (not SEO), so a client fetch
                 here is fine — no Vue. --}}
            <div data-cart-page>
                <div class="row">
                    <div class="col-xl-8">
                        <p class="text-center py-5" data-cart-empty style="display:none">
                            Your cart is empty. <a href="/search" class="link">Continue shopping</a>
                        </p>
                        <table class="tf-table-page-cart" data-cart-table style="display:none">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Price</th>
                                    <th class="text-center">Quantity</th>
                                    <th class="text-center">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-cart-rows></tbody>
                        </table>
                    </div>

                    <div class="col-xl-4">
                        <div class="fl-sidebar-cart">
                            <div class="box-order bg-surface">
                                <h5 class="title">Order Summary</h5>

                                <div class="free-ship-bar mb_16" data-cart-freeship style="display:none">
                                    <p class="text-button mb_8" data-cart-freeship-text></p>
                                    <div class="progress-track">
                                        <div class="progress-fill" data-cart-freeship-fill style="width:0%"></div>
                                    </div>
                                </div>

                                <div class="coupon-box mb_16" data-cart-coupon-box style="display:none">
                                    <div class="d-flex justify-content-between align-items-center" data-cart-coupon-applied style="display:none">
                                        <span class="text-button">Coupon: <strong data-cart-coupon-code></strong></span>
                                        <button type="button" class="link text-button" data-cart-coupon-remove>Remove</button>
                                    </div>
                                    <div data-cart-coupon-entry>
                                        <form class="d-flex gap-2" data-cart-coupon-form>
                                            <input type="text" name="code" placeholder="Coupon code" class="flex-grow-1">
                                            <button type="submit" class="tf-btn btn-outline">Apply</button>
                                        </form>
                                        <div class="available-coupons mt_8" data-cart-coupons style="display:none">
                                            <span class="text-secondary small">Available:</span>
                                            <span data-cart-coupons-list></span>
                                        </div>
                                    </div>
                                    <p class="text-danger small mt_8" data-cart-coupon-error style="display:none"></p>
                                </div>

                                <div class="subtotal text-button d-flex justify-content-between align-items-center">
                                    <span>Subtotal</span>
                                    <span class="total" data-cart-subtotal>—</span>
                                </div>
                                <div class="discount text-button d-flex justify-content-between align-items-center" data-cart-discount-row style="display:none">
                                    <span>Discount</span>
                                    <span class="total" data-cart-discount></span>
                                </div>
                                <div class="tax text-button d-flex justify-content-between align-items-center" data-cart-tax-row style="display:none">
                                    <span>Tax</span>
                                    <span class="total" data-cart-tax></span>
                                </div>
                                <div class="total-cart text-button d-flex justify-content-between align-items-center mt_12">
                                    <span>Total</span>
                                    <span class="total" data-cart-total>—</span>
                                </div>
                                <a href="/checkout" class="tf-btn btn-fill w-100 mt_16" data-cart-checkout>
                                    <span class="text">Check out</span>
                                </a>
                                <a href="/search" class="tf-btn btn-outline w-100 mt_8">
                                    <span class="text">Continue shopping</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
