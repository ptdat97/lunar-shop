@extends('theme::layouts.app')

@section('title', 'Login')

@section('content')
    <div class="page-title">
        <div class="container-full"><div class="row"><div class="col-12">
            <h3 class="heading text-center">Login</h3>
        </div></div></div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            <div class="login-wrap">
                <div class="left">
                    <div class="heading"><h4>Login</h4></div>
                    <div data-vue="auth-form" data-mode="login" data-redirect="/account"></div>
                </div>
                <div class="right">
                    <h4 class="mb_8">New Customer</h4>
                    <p class="text-secondary">Create an account to enjoy faster checkout, order history and your wishlist.</p>
                    <a href="{{ route('storefront.register') }}" class="tf-btn btn-fill"><span class="text text-button">Register</span></a>
                </div>
            </div>
        </div>
    </section>
@endsection
