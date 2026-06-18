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
                    <form class="form-login form-has-password" data-auth-form data-endpoint="/api/v1/auth/register" data-redirect="/account" data-confirm-password>
                        @csrf
                        <div class="wrap">
                            <fieldset>
                                <input type="text" name="name" placeholder="Full name*" required>
                                <span class="text-danger small" data-error-for="name"></span>
                            </fieldset>
                            <fieldset>
                                <input type="email" name="email" placeholder="Email address*" required>
                                <span class="text-danger small" data-error-for="email"></span>
                            </fieldset>
                            <fieldset class="position-relative password-item">
                                <input type="password" name="password" class="input-password" placeholder="Password*" required>
                                <span class="text-danger small" data-error-for="password"></span>
                            </fieldset>
                            <fieldset class="position-relative password-item">
                                <input type="password" name="password_confirmation" class="input-password" placeholder="Confirm password*" required>
                            </fieldset>
                        </div>
                        <p class="text-danger" data-form-message></p>
                        <div class="button-submit">
                            <button class="tf-btn btn-fill" type="submit"><span class="text text-button">Register</span></button>
                        </div>
                    </form>
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
