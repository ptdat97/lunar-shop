{{-- Responsive <picture>. Included with:
       ['picture' => $payload, 'alt' => '...', 'class' => '...', 'sizes' => '...',
        'loading' => 'lazy'|'eager', 'fetchpriority' => 'high'|null]
     $payload = MediaUrl::responsive() → { src, srcset, webp, width, height }.
     Resolved by a view composer / service (standards §7) — never in the view.
     Falls back to a plain <img> when no payload (keeps SSR + no-JS working). --}}
@php
    $picture = $picture ?? null;
    $alt = $alt ?? '';
    $class = $class ?? '';
    $loading = $loading ?? 'lazy';
    $sizes = $sizes ?? '100vw';
    $fetchpriority = $fetchpriority ?? null;
@endphp
@if($picture && ($picture['src'] ?? null))
    <picture>
        @if(!empty($picture['webp']))
            <source type="image/webp" srcset="{{ $picture['webp'] }}" sizes="{{ $sizes }}">
        @endif
        @if(!empty($picture['srcset']))
            <source srcset="{{ $picture['srcset'] }}" sizes="{{ $sizes }}">
        @endif
        <img src="{{ $picture['src'] }}" alt="{{ $alt }}" class="{{ $class }}"
             @if(($picture['width'] ?? 0) > 0) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif
             loading="{{ $loading }}" decoding="async"
             @if($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif>
    </picture>
@endif
