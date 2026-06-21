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
    <meta name="robots" content="@yield('robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">

    {{-- Open Graph / Twitter. Pages override og:type/og:image via @section. --}}
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', View::yieldContent('title', config('app.name')))">
    <meta property="og:description" content="@yield('og_description', View::yieldContent('meta_description'))">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    @if($favicon = $theme->image('general.favicon'))
        <link rel="icon" href="{{ $favicon }}">
    @endif

    {{-- Vendor CSS (public/vendor) --}}
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}">

    {{-- Theme CSS/JS (Vite) --}}
    @vite(['themes/fashion/css/app.scss', 'themes/fashion/js/app.js'])

    @stack('head')
</head>
<body class="d-flex flex-column min-vh-100">
    <a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 m-2 btn btn-dark btn-sm">
        Skip to content
    </a>

    @include('theme::partials.header')

    @include('theme::partials.flash')

    <main class="flex-grow-1" id="main-content" tabindex="-1">
        @yield('content')
    </main>

    @include('theme::partials.footer')

    @include('theme::partials.cart-drawer')

    {{-- Vendor JS (public/vendor). Vue is imported on demand by app.js. --}}
    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}"></script>

    @stack('scripts')
</body>
</html>
