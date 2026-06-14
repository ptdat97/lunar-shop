@props([
    'media' => null,      // Spatie Media model (e.g. $product->thumbnail)
    'alt' => '',
    'conversion' => 'medium', // preferred <img> conversion
    'loading' => 'lazy',
])

@php
    // Fall back to the original URL if the requested conversion isn't generated.
    $src = null;
    if ($media) {
        $src = $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
    $webp = $media && $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : null;
@endphp

@if ($src)
    <picture>
        @if ($webp)
            <source type="image/webp" srcset="{{ $webp }}">
        @endif

        <img src="{{ $src }}" alt="{{ $alt }}" loading="{{ $loading }}" {{ $attributes }}>
    </picture>
@else
    <div {{ $attributes->merge(['class' => 'image-placeholder']) }}></div>
@endif
