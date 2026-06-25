@extends('theme::layouts.app')

@section('title', __('storefront.auth.sign_in').' — '.config('app.name'))
@section('robots', 'noindex, follow')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <h1 class="h3 mb-4 text-center">{{ __('storefront.auth.sign_in') }}</h1>

            {{-- Auth is API-driven (Sanctum SPA cookie). enhance/auth.js posts to
                 /api/v1/auth/login and redirects on success. --}}
            <form data-auth-form="login" novalidate>
                <div class="alert alert-danger" role="alert" data-auth-error hidden></div>

                <div class="mb-3">
                    <label class="form-label" for="email">{{ __('storefront.common.email') }}</label>
                    <input class="form-control" type="email" id="email" name="email" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">{{ __('storefront.auth.password') }}</label>
                    <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">{{ __('storefront.auth.remember_me') }}</label>
                </div>

                <button class="btn btn-dark w-100" type="submit" data-auth-submit>{{ __('storefront.auth.sign_in') }}</button>
            </form>

            <p class="text-center mt-3 mb-0">
                {{ __('storefront.auth_extra.no_account') }} <a href="{{ route('storefront.register') }}">{{ __('storefront.auth.create_one') }}</a>
            </p>
        </div>
    </div>
</div>
@endsection
