{{-- Marketing pixels (Google / Facebook). Injected into the <head> by the layout.
     Both pixels are optional — the partial renders nothing when the IDs are blank. --}}
@php
    $googlePixel = $theme->get('pixels.google');
    $facebookPixel = $theme->get('pixels.facebook');
@endphp

@if($googlePixel || $facebookPixel)
    <!-- Marketing Pixels -->
    @if($googlePixel)
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googlePixel }}"
                data-pixel-google="{{ $googlePixel }}"></script>
        <script data-pixel-google="{{ $googlePixel }}">
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $googlePixel }}');
        </script>
    @endif

    @if($facebookPixel)
        <!-- Facebook Pixel Code -->
        <script data-pixel-facebook="{{ $facebookPixel }}">
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ $facebookPixel }}');
            fbq('track', 'PageView');
        </script>
        <noscript>
            <img height="1" width="1" style="display:none"
                 src="https://www.facebook.com/tr?id={{ $facebookPixel }}&ev=PageView&noscript=1"/>
        </noscript>
    @endif
@endif
