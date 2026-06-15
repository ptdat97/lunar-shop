@extends('theme::layouts.app')

@section('title', 'My Account')

@section('content')
    <div class="page-title">
        <div class="container-full"><div class="row"><div class="col-12">
            <h3 class="heading text-center">My Account</h3>
        </div></div></div>
    </div>

    <section class="flat-spacing">
        <div class="container">
            <div class="account-info">
                <h5>Hello, {{ $user->name }}</h5>
                <p class="text-secondary">{{ $user->email }}</p>

                <ul class="account-links mt_20">
                    <li><a href="{{ route('storefront.wishlist') }}" class="link">My Wishlist</a></li>
                    <li><a href="/search" class="link">Continue shopping</a></li>
                </ul>

                {{-- Logout via the auth API --}}
                <div data-vue="logout-button" class="mt_20"></div>
            </div>
        </div>
    </section>
@endsection
