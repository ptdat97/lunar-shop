<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name'))</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="@yield('meta_description', config('app.name'))">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Modave vendor styles (Bootstrap 5 + Swiper + animate) served from public --}}
    <link rel="stylesheet" href="/themes/modave/fonts/fonts.css">
    <link rel="stylesheet" href="/themes/modave/fonts/font-icons.css">
    <link rel="stylesheet" href="/themes/modave/css/bootstrap.min.css">
    <link rel="stylesheet" href="/themes/modave/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="/themes/modave/css/animate.css">
    <link rel="stylesheet" type="text/css" href="/themes/modave/css/styles.css">

    {{-- Theme app JS (Vue islands + axios), built by Vite --}}
    @vite(['themes/modave/js/app.js'])

    <link rel="shortcut icon" href="/themes/modave/images/logo/favicon.png">
</head>

<body class="preload-wrapper">
    <button id="scroll-top">
        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 11.9175L12 2.91748L21 11.9175H16.5V20.1675C16.5 20.3664 16.421 20.5572 16.2803 20.6978C16.1397 20.8385 15.9489 20.9175 15.75 20.9175H8.25C8.05109 20.9175 7.86032 20.8385 7.71967 20.6978C7.57902 20.5572 7.5 20.3664 7.5 20.1675V11.9175H3Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div class="preload preload-container">
        <div class="preload-logo"><div class="spinner"></div></div>
    </div>

    <div id="wrapper">
        @include('theme::components.header')

        @yield('content')

        @include('theme::components.footer')
    </div>

    @include('theme::components.modals')

    {{-- Modave vendor scripts (jQuery + Swiper + plugins) --}}
    <script src="/themes/modave/js/bootstrap.min.js"></script>
    <script src="/themes/modave/js/jquery.min.js"></script>
    <script src="/themes/modave/js/swiper-bundle.min.js"></script>
    <script src="/themes/modave/js/carousel.js"></script>
    <script src="/themes/modave/js/bootstrap-select.min.js"></script>
    <script src="/themes/modave/js/lazysize.min.js"></script>
    <script src="/themes/modave/js/count-down.js"></script>
    <script src="/themes/modave/js/wow.min.js"></script>
    <script src="/themes/modave/js/multiple-modal.js"></script>
    <script src="/themes/modave/js/main.js"></script>
</body>
</html>
