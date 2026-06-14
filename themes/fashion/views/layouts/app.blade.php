<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['themes/fashion/css/app.css', 'themes/fashion/js/app.js'])
</head>
<body class="antialiased">
    <header>
        @include('theme::components.header')
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        @include('theme::components.footer')
    </footer>
</body>
</html>
