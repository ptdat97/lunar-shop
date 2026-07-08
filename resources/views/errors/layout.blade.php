{{--
    Shared shell for production error pages. Deliberately self-contained:
    no theme layout, no view composers, no DB — a 500/503 must render even
    when the database or the module bootstrap is the thing that's broken.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <style>
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
               background: #fafafa; color: #1a1a1a; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
        .error-card { text-align: center; padding: 3rem 1.5rem; max-width: 34rem; }
        .error-code { font-size: 5rem; font-weight: 200; letter-spacing: .1em; margin: 0; color: #999; }
        h1 { font-size: 1.5rem; font-weight: 600; margin: .75rem 0; }
        p { color: #555; line-height: 1.6; margin: 0 0 2rem; }
        a.home { display: inline-block; padding: .75rem 2rem; background: #1a1a1a; color: #fff;
                 text-decoration: none; font-size: .875rem; letter-spacing: .05em; text-transform: uppercase; }
        a.home:hover { background: #333; }
    </style>
</head>
<body>
    <div class="error-card">
        <p class="error-code">@yield('code')</p>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="home" href="{{ url('/') }}">{{ __('storefront.errors.go_home') }}</a>
    </div>
</body>
</html>
