{{-- Minimal checkout shell (Shopify-style): logo + step breadcrumb only, no
     site nav / footer / cart drawer — a distraction-free conversion surface.
     Pages still render real SSR HTML; JS only enhances (address, coupon). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('storefront.cart.checkout').' — '.config('app.name'))</title>
    <meta name="robots" content="noindex, nofollow">

    @if($favicon = $theme->image('general.favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif

    @vite(['themes/fashion/css/app.scss', 'themes/fashion/js/app.js'])

    @stack('head')
</head>
<body class="checkout-shell d-flex flex-column min-vh-100 @yield('body_class')">
    <header class="checkout-header border-bottom bg-white">
        <div class="checkout-container py-3 d-flex flex-column align-items-center gap-2">
            <a class="navbar-brand fw-bold text-uppercase m-0 fs-4" href="{{ route('storefront.home') }}">
                @if($logo = $theme->image('general.logo'))
                    <img src="{{ $logo }}" alt="{{ config('app.name') }}" height="36"
                         onerror="this.replaceWith(document.createTextNode('{{ config('app.name') }}'))">
                @else
                    {{ config('app.name') }}
                @endif
            </a>

            {{-- Step breadcrumb: Cart › Information › Payment --}}
            <nav class="checkout-steps small" aria-label="Checkout steps">
                <a href="{{ route('storefront.cart') }}" class="checkout-steps__step text-muted text-decoration-none">
                    {{ __('storefront.checkout.step_cart') }}
                </a>
                <i class="bi bi-chevron-right text-muted mx-1"></i>
                <span class="checkout-steps__step checkout-steps__step--current fw-semibold">
                    {{ __('storefront.checkout.step_information') }}
                </span>
                <i class="bi bi-chevron-right text-muted mx-1"></i>
                <span class="checkout-steps__step text-muted">
                    {{ __('storefront.checkout.step_payment') }}
                </span>
            </nav>
        </div>
    </header>

    <main class="flex-grow-1" id="main-content">
        @yield('content')
    </main>

    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
