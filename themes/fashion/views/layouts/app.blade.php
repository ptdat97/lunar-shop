{{-- Storefront shell. SSR-first: pages render real HTML; JS only enhances.
     Bootstrap 5 + Bootstrap Icons + jQuery + Swiper load from public/vendor.
     The theme's own CSS/JS bundle through Vite (@vite). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $theme->get('general.site_name', config('app.name')))</title>
    <meta name="description" content="@yield('meta_description', $theme->get('seo.description', ''))">

    @if($favicon = $theme->image('general.favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif

    {{-- Vendor CSS (public/vendor) --}}
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}">

    {{-- Theme CSS/JS (Vite) --}}
    @vite(['themes/fashion/css/app.css', 'themes/fashion/js/app.js'])

    @stack('head')
</head>
<body class="d-flex flex-column min-vh-100">
    @include('theme::partials.header')

    @include('theme::partials.flash')

    <main class="flex-grow-1">
        @yield('content')
    </main>

    @include('theme::partials.footer')

    @include('theme::partials.cart-drawer')

    {{-- Vendor JS (public/vendor). Vue is imported on demand by app.js. --}}
    <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}"></script>

    @stack('scripts')
</body>
</html>
