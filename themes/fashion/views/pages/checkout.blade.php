@extends('theme::layouts.app')

@section('title', 'Checkout — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-4">
    <h1 class="h3 mb-4">Checkout</h1>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- One SSR form: address + shipping + payment submit together. No client
         multi-step state, so the order is placed in a single server round-trip.
         Vanilla JS only enhances the Province→Ward dependent dropdowns. --}}
    <form method="POST" action="{{ route('storefront.checkout.place') }}" class="row g-4"
          data-checkout-form data-wards-url="{{ url('/api/v1/locations/provinces') }}">
        @csrf
        <div class="col-12 col-lg-7">
            {{-- Address --}}
            <section class="border rounded p-3 mb-3">
                <h2 class="h6 text-uppercase">Shipping address</h2>
                <div class="row g-2">
                    <div class="col-6">
                        <input name="first_name" value="{{ old('first_name') }}" class="form-control" placeholder="Họ" required>
                    </div>
                    <div class="col-6">
                        <input name="last_name" value="{{ old('last_name') }}" class="form-control" placeholder="Tên" required>
                    </div>

                    <div class="col-6">
                        <select class="form-select" data-province required>
                            <option value="" disabled @selected(!old('state'))>Tỉnh/Thành phố</option>
                            @foreach($provinces as $p)
                                <option value="{{ $p->id }}" data-name="{{ $p->name }}" @selected(old('state') === $p->name)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <select class="form-select" data-ward required @disabled(!old('state'))>
                            <option value="" disabled @selected(!old('city'))>Phường/Xã</option>
                            @if(old('city'))
                                <option value="{{ old('city') }}" data-name="{{ old('city') }}" selected>{{ old('city') }}</option>
                            @endif
                        </select>
                    </div>

                    {{-- Lunar stores province in state, ward in city. The selects
                         above write the chosen NAMES into these hidden fields. --}}
                    <input type="hidden" name="state" data-state value="{{ old('state') }}">
                    <input type="hidden" name="city" data-city value="{{ old('city') }}">

                    <div class="col-12">
                        <input name="line_one" value="{{ old('line_one') }}" class="form-control" placeholder="Số nhà, tên đường" required>
                    </div>
                    <div class="col-12">
                        <select name="country_id" class="form-select" required>
                            @foreach($countries as $c)
                                <option value="{{ $c['id'] }}" @selected((int) old('country_id') === (int) $c['id'])>{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <input name="contact_email" type="email" value="{{ old('contact_email') }}" class="form-control" placeholder="Email" required>
                    </div>
                    <div class="col-6">
                        <input name="contact_phone" value="{{ old('contact_phone') }}" class="form-control" placeholder="Số điện thoại" required>
                    </div>
                </div>
            </section>

            {{-- Shipping --}}
            <section class="border rounded p-3 mb-3">
                <h2 class="h6 text-uppercase">Shipping method</h2>
                @forelse($shippingOptions as $opt)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="shipping_option"
                               id="ship-{{ $opt->getIdentifier() }}" value="{{ $opt->getIdentifier() }}"
                               @checked(old('shipping_option', $loop->first ? $opt->getIdentifier() : null) === $opt->getIdentifier()) required>
                        <label class="form-check-label d-flex justify-content-between" for="ship-{{ $opt->getIdentifier() }}">
                            <span>{{ $opt->name }}</span><span>{{ $opt->price->formatted() }}</span>
                        </label>
                    </div>
                @empty
                    <div class="text-muted small">No shipping options available.</div>
                @endforelse
            </section>

            {{-- Payment --}}
            <section class="border rounded p-3">
                <h2 class="h6 text-uppercase">Payment</h2>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_type" id="pay-cod" value="cod"
                           @checked(old('payment_type', 'cod') === 'cod') required>
                    <label class="form-check-label" for="pay-cod">Cash on delivery</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_type" id="pay-bank" value="bank-transfer"
                           @checked(old('payment_type') === 'bank-transfer')>
                    <label class="form-check-label" for="pay-bank">Bank transfer</label>
                </div>
                @if($vnpayEnabled)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_type" id="pay-vnpay" value="vnpay"
                               @checked(old('payment_type') === 'vnpay')>
                        <label class="form-check-label" for="pay-vnpay">VNPay (online payment)</label>
                    </div>
                @endif
            </section>
        </div>

        {{-- Order summary --}}
        <div class="col-12 col-lg-5">
            <div class="border rounded p-3">
                <h2 class="h6 text-uppercase">Order summary</h2>
                @foreach($cart->lines as $line)
                    <div class="d-flex justify-content-between small py-1">
                        <span>{{ $line->purchasable->getDescription() }} × {{ $line->quantity }}</span>
                        <span>{{ $line->subTotal->formatted() }}</span>
                    </div>
                @endforeach
                <dl class="row small border-top pt-2 mt-2 mb-0">
                    <dt class="col-7 fw-normal">Subtotal</dt><dd class="col-5 text-end">{{ $cart->subTotal?->formatted() }}</dd>
                    <dt class="col-7 fw-normal">Tax</dt><dd class="col-5 text-end">{{ $cart->taxTotal?->formatted() }}</dd>
                    <dt class="col-7 fw-bold">Total</dt><dd class="col-5 text-end fw-bold">{{ $cart->total?->formatted() }}</dd>
                </dl>
                <button class="btn btn-dark w-100 mt-3" type="submit">Place order</button>
            </div>
        </div>
    </form>
</div>
@endsection
