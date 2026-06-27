@extends('theme::layouts.app')
@section('body_class', 'page-register')

@section('title', __('storefront.auth.create_account').' — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <h1 class="h3 mb-4 text-center">{{ __('storefront.auth.create_account') }}</h1>

            <form data-auth-form="register" novalidate>
                <div class="alert alert-danger" role="alert" data-auth-error hidden></div>

                <div class="mb-3">
                    <label class="form-label" for="name">{{ __('storefront.common.name') }}</label>
                    <input class="form-control" type="text" id="name" name="name" required autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('storefront.common.email') }}</label>
                    <input class="form-control" type="email" id="email" name="email" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('storefront.auth.password') }}</label>
                    <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">{{ __('storefront.auth.min_chars') }}</div>
                </div>

                <button class="btn btn-dark w-100" type="submit" data-auth-submit>{{ __('storefront.auth.create_account') }}</button>
            </form>

            <p class="text-center mt-3 mb-0">
                {{ __('storefront.auth_extra.have_account') }} <a href="{{ route('storefront.login') }}">{{ __('storefront.auth.sign_in') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
