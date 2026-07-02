{{-- Recently viewed strip. Personalised (localStorage) → not SEO content, so
     it's rendered client-side by enhance/recently-viewed.js (fetch-on-load is
     allowed for session/personalised content, like wishlist membership).

     On a product page, pass ['currentSlug' => $slug]: the enhancer records that
     slug as viewed and excludes it from the strip. Elsewhere (e.g. home) omit it
     and the enhancer just renders whatever is stored. Hidden until ≥1 item. --}}
@php
    $currentSlug = $currentSlug ?? null;
    // $recentlyViewedLimit injected by the Catalog view composer (§7); default
    // to 8 when the partial is rendered outside that composer's reach.
    $recentlyViewedLimit = $recentlyViewedLimit ?? 8;
@endphp
<section class="recently-viewed mt-5" data-recently-viewed
         @if($currentSlug) data-current-slug="{{ $currentSlug }}" @endif
         data-limit="{{ $recentlyViewedLimit }}" hidden>
    <h2 class="h4 mb-3">{{ __('storefront.product.recently_viewed') }}</h2>
    <div class="row g-4" data-recently-viewed-grid></div>
</section>
