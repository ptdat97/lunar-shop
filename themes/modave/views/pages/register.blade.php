@extends('theme::layouts.app')

@section('title', 'Register')

@section('content')
    <div class="page-title">
        <div class="container-full"><div class="row"><div class="col-12">
            <h3 class="heading text-center">Register</h3>
        </div></div></div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            <div class="login-wrap">
                <div class="left">
                    <div class="heading"><h4>Create an account</h4></div>
                    <div data-vue="auth-form" data-mode="register" data-redirect="/account"></div>
                </div>
                <div class="right">
                    <h4 class="mb_8">Already have an account?</h4>
                    <p class="text-secondary">Log in to access your orders, wishlist and faster checkout.</p>
                    <a href="{{ route('storefront.login') }}" class="tf-btn btn-fill"><span class="text text-button">Login</span></a>
                </div>
            </div>
        </div>
    </section>
@endsection
