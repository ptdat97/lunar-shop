@php
    // Settings-only section (no Lunar provider). `posts` is an optional array of
    // { image, url } injected from admin; if empty we fall back to the demo set so
    // a freshly seeded home page still shows the grid. The "follow" CTA points at
    // the Instagram social link configured in theme settings, when present.
    $posts = $settings['posts'] ?? [];

    if (empty($posts)) {
        $posts = collect(['DTT_9602', 'DTT_9618', 'DTT_9009', 'DTT_9123', 'DTT_9150', 'IMG_5805'])
            ->map(fn ($n) => ['image' => "/demo/{$n}.jpg", 'url' => null])
            ->all();
    }

    $instagram = collect($theme->get('social', []))
        ->first(fn ($s) => str_contains(strtolower($s['icon'] ?? ''), 'instagram'));
    $instagramUrl = $instagram['url'] ?? null;
@endphp
@if($posts)
    <section class="instagram container-fluid px-0 my-5">
        <div class="text-center mb-4">
            @if(!empty($settings['heading']))
                <h2 class="h4 mb-1">{{ $settings['heading'] }}</h2>
            @endif
            @if(!empty($settings['subheading']))
                <p class="text-muted mb-2">{{ $settings['subheading'] }}</p>
            @endif
            @if($instagramUrl)
                <a href="{{ $instagramUrl }}" target="_blank" rel="noopener"
                   class="d-inline-flex align-items-center gap-2 fw-semibold text-dark text-decoration-none">
                    <i class="bi bi-instagram"></i>
                    {{ $instagram['handle'] ?? __('storefront.common.follow_us') }}
                </a>
            @endif
        </div>

        <div class="instagram__grid">
            @foreach($posts as $post)
                @php $href = $post['url'] ?? $instagramUrl ?? '#'; @endphp
                <a href="{{ $href }}" class="instagram__item"
                   @if($href !== '#') target="_blank" rel="noopener" @endif
                   aria-label="Instagram">
                    <img src="{{ $post['image'] ?? '' }}" alt="" loading="lazy">
                    <span class="instagram__overlay"><i class="bi bi-instagram"></i></span>
                </a>
            @endforeach
        </div>
    </section>
@endif
