@extends('theme::layouts.checkout')

@section('title', __('storefront.cart.checkout').' — '.config('app.name'))

@section('content')
{{-- Shopify-style two-column checkout. One SSR form: address + shipping +
     payment submit together (single server round-trip — no client multi-step
     state). Left column is the form on white; right column is a sticky grey
     order summary with line thumbnails. Vanilla JS enhances the Province→Ward
     dropdowns and the coupon; every data-* hook below is consumed by the
     existing enhancers (checkout-address.js / checkout-coupon.js). --}}
<form method="POST" action="{{ route('storefront.checkout.place') }}" class="checkout"
      data-checkout-form data-wards-url="{{ url('/api/v1/locations/provinces') }}">
    @csrf

    <div class="checkout__main">
        <div class="checkout__form">
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Contact --}}
            <section class="checkout-block">
                <h2 class="checkout-block__title">{{ __('storefront.checkout.contact') }}</h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="form-floating">
                            <input name="contact_email" type="email" id="co-email" value="{{ old('contact_email') }}"
                                   class="form-control" placeholder="Email" required>
                            <label for="co-email">{{ __('storefront.checkout.email') }}</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating">
                            <input name="contact_phone" id="co-phone" value="{{ old('contact_phone') }}"
                                   class="form-control" placeholder="Phone" required>
                            <label for="co-phone">{{ __('storefront.checkout.phone') }}</label>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Delivery / address --}}
            <section class="checkout-block">
                <h2 class="checkout-block__title">{{ __('storefront.checkout.delivery') }}</h2>
                <div class="row g-3">
                    <div class="col-6">
                        <div class="form-floating">
                            <input name="first_name" id="co-first" value="{{ old('first_name') }}"
                                   class="form-control" placeholder="First name" required>
                            <label for="co-first">{{ __('storefront.checkout.first_name') }}</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating">
                            <input name="last_name" id="co-last" value="{{ old('last_name') }}"
                                   class="form-control" placeholder="Last name" required>
                            <label for="co-last">{{ __('storefront.checkout.last_name') }}</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating">
                            <select name="country_id" id="co-country" class="form-select" required>
                                @foreach($countries as $c)
                                    <option value="{{ $c['id'] }}" @selected((int) old('country_id') === (int) $c['id'])>{{ $c['name'] }}</option>
                                @endforeach
                            </select>
                            <label for="co-country">{{ __('storefront.checkout.country') }}</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating">
                            <input name="line_one" id="co-line" value="{{ old('line_one') }}"
                                   class="form-control" placeholder="Address" required>
                            <label for="co-line">{{ __('storefront.checkout.address') }}</label>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="form-floating">
                            <select class="form-select" id="co-province" data-province required>
                                <option value="" disabled @selected(!old('state'))></option>
                                @foreach($provinces as $p)
                                    <option value="{{ $p->id }}" data-name="{{ $p->name }}" @selected(old('state') === $p->name)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                            <label for="co-province">{{ __('storefront.checkout.province') }}</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-floating">
                            <select class="form-select" id="co-ward" data-ward required @disabled(!old('state'))>
                                <option value="" disabled @selected(!old('city'))></option>
                                @if(old('city'))
                                    <option value="{{ old('city') }}" data-name="{{ old('city') }}" selected>{{ old('city') }}</option>
                                @endif
                            </select>
                            <label for="co-ward">{{ __('storefront.checkout.ward') }}</label>
                        </div>
                    </div>

                    {{-- Lunar stores province in `state`, ward in `city`. The selects
                         above write the chosen NAMES into these hidden fields. --}}
                    <input type="hidden" name="state" data-state value="{{ old('state') }}">
                    <input type="hidden" name="city" data-city value="{{ old('city') }}">
                </div>
            </section>

            {{-- Shipping method --}}
            <section class="checkout-block">
                <h2 class="checkout-block__title">{{ __('storefront.checkout.shipping_method') }}</h2>
                <div class="checkout-options">
                    @forelse($shippingOptions as $opt)
                        @php $checked = old('shipping_option', $loop->first ? $opt->getIdentifier() : null) === $opt->getIdentifier(); @endphp
                        <label class="checkout-option @if($checked) checkout-option--active @endif" for="ship-{{ $opt->getIdentifier() }}">
                            <input class="form-check-input m-0" type="radio" name="shipping_option"
                                   id="ship-{{ $opt->getIdentifier() }}" value="{{ $opt->getIdentifier() }}"
                                   @checked($checked) required data-shipping-radio>
                            <span class="checkout-option__label">{{ $opt->name }}</span>
                            <span class="checkout-option__price">{{ $opt->price->formatted() }}</span>
                        </label>
                    @empty
                        <div class="text-muted small">{{ __('storefront.checkout.no_shipping_options') }}</div>
                    @endforelse
                </div>
            </section>

            {{-- Payment --}}
            <section class="checkout-block">
                <h2 class="checkout-block__title">{{ __('storefront.checkout.payment') }}</h2>
                <p class="text-muted small d-flex align-items-center gap-1 mb-2">
                    <i class="bi bi-lock-fill"></i> {{ __('storefront.checkout.payment_secure') }}
                </p>
                <div class="checkout-options">
                    @php $pay = old('payment_type', 'cod'); @endphp
                    <label class="checkout-option @if($pay === 'cod') checkout-option--active @endif" for="pay-cod">
                        <input class="form-check-input m-0" type="radio" name="payment_type" id="pay-cod" value="cod"
                               @checked($pay === 'cod') required data-payment-radio>
                        <span class="checkout-option__label">
                            {{ __('storefront.checkout.cod') }}
                            <small class="d-block text-muted">{{ __('storefront.checkout.cod_hint') }}</small>
                        </span>
                        <i class="bi bi-cash-coin checkout-option__icon"></i>
                    </label>

                    <label class="checkout-option @if($pay === 'bank-transfer') checkout-option--active @endif" for="pay-bank">
                        <input class="form-check-input m-0" type="radio" name="payment_type" id="pay-bank" value="bank-transfer"
                               @checked($pay === 'bank-transfer') data-payment-radio>
                        <span class="checkout-option__label">
                            {{ __('storefront.checkout.bank_transfer') }}
                            <small class="d-block text-muted">{{ __('storefront.checkout.bank_transfer_hint') }}</small>
                        </span>
                        <i class="bi bi-bank checkout-option__icon"></i>
                    </label>

                    @if($vnpayEnabled)
                        <label class="checkout-option @if($pay === 'vnpay') checkout-option--active @endif" for="pay-vnpay">
                            <input class="form-check-input m-0" type="radio" name="payment_type" id="pay-vnpay" value="vnpay"
                                   @checked($pay === 'vnpay') data-payment-radio>
                            <span class="checkout-option__label">
                                {{ __('storefront.checkout.vnpay') }}
                                <small class="d-block text-muted">{{ __('storefront.checkout.vnpay_hint') }}</small>
                            </span>
                            <i class="bi bi-credit-card checkout-option__icon"></i>
                        </label>
                    @endif
                </div>
            </section>

            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">
                <a href="{{ route('storefront.cart') }}" class="checkout__back text-decoration-none">
                    <i class="bi bi-chevron-left"></i> {{ __('storefront.checkout.back_to_cart') }}
                </a>
                <button class="btn btn-dark btn-lg checkout__submit px-4" type="submit">
                    {{ __('storefront.checkout.place_order') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Order summary (sticky, grey) --}}
    <aside class="checkout__aside">
        <div class="checkout-summary" data-checkout-summary>
            <ul class="checkout-summary__lines list-unstyled mb-0">
                @foreach($cart->lines as $line)
                    @php $img = $lineImage($line); @endphp
                    <li class="checkout-summary__line">
                        <div class="checkout-summary__media">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $line->purchasable->getDescription() }}" loading="lazy">
                            @endif
                            <span class="checkout-summary__qty">{{ $line->quantity }}</span>
                        </div>
                        <div class="checkout-summary__info">
                            <span class="checkout-summary__name">{{ $line->purchasable->getDescription() }}</span>
                        </div>
                        <span class="checkout-summary__price">{{ $line->subTotal->formatted() }}</span>
                    </li>
                @endforeach
            </ul>

            {{-- Coupon — applied via /api/v1/cart/coupon (same endpoint as the cart
                 page). Stored on the server-side cart session so placeOrder()
                 picks it up; the summary updates in place without reloading. --}}
            <div class="checkout-summary__coupon" data-coupon-form>
                <div class="input-group">
                    <input class="form-control" placeholder="{{ __('storefront.cart.coupon_code') }}" data-coupon-input
                           value="{{ $cart->coupon_code }}">
                    @if($cart->coupon_code)
                        <button class="btn btn-outline-danger" type="button" data-coupon-remove>{{ __('storefront.common.remove') }}</button>
                    @else
                        <button class="btn btn-outline-dark" type="button" data-coupon-apply>{{ __('storefront.common.apply') }}</button>
                    @endif
                </div>
                <div class="small mt-1" data-coupon-status></div>
            </div>

            {{-- Applied promotions (SSR — cart already calculated). $appliedDiscounts
                 injected by the Promotion view composer. checkout-coupon.js
                 refreshes this list on coupon apply/remove without a reload. --}}
            <div class="checkout-summary__discounts" data-checkout-discounts>
                @foreach($appliedDiscounts as $promo)
                    <div class="d-flex justify-content-between small text-success mb-1">
                        <span>
                            @if($promo['is_flash_sale'])<i class="bi bi-lightning-charge-fill"></i>@else<i class="bi bi-tag"></i>@endif
                            {{ $promo['name'] }}
                            @if($promo['description'] && $promo['description'] !== $promo['name'])
                                <span class="text-muted">({{ $promo['description'] }})</span>
                            @endif
                        </span>
                        <span>−{{ $promo['amount'] }}</span>
                    </div>
                @endforeach
            </div>

            <dl class="checkout-summary__totals mb-0">
                <div class="checkout-summary__row">
                    <dt>{{ __('storefront.cart.subtotal') }}</dt>
                    <dd data-sum-subtotal>{{ $cart->subTotal?->formatted() }}</dd>
                </div>
                <div class="checkout-summary__row" data-discount-row @class(['d-none' => ! $cart->discountTotal?->value])>
                    <dt>{{ __('storefront.cart.discount') }}</dt>
                    <dd class="text-success" data-sum-discount>−{{ $cart->discountTotal?->formatted() }}</dd>
                </div>
                <div class="checkout-summary__row">
                    <dt>{{ __('storefront.cart.tax') }}</dt>
                    <dd data-sum-tax>{{ $cart->taxTotal?->formatted() }}</dd>
                </div>
                <div class="checkout-summary__row checkout-summary__row--total">
                    <dt>{{ __('storefront.cart.total') }}</dt>
                    <dd data-sum-total>{{ $cart->total?->formatted() }}</dd>
                </div>
            </dl>
        </div>
    </aside>
</form>
@endsection
