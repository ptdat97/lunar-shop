@extends('theme::layouts.app')

@section('title', 'Create account — '.config('app.name'))

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <h1 class="h3 mb-4 text-center">Create account</h1>

            <form data-auth-form="register" novalidate>
                <div class="alert alert-danger" role="alert" data-auth-error hidden></div>

                <div class="mb-3">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" type="text" id="name" name="name" required autocomplete="name">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <div class="form-text">At least 8 characters.</div>
                </div>

                <button class="btn btn-dark w-100" type="submit" data-auth-submit>Create account</button>
            </form>

            <p class="text-center mt-3 mb-0">
                Already have an account? <a href="{{ route('storefront.login') }}">Sign in</a>
            </p>
        </div>
    </div>
</div>
@endsection
