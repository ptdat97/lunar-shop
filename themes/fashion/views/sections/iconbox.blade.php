@php
    $items = $settings['items'] ?? [];
    // Map the template's icon handles to Bootstrap Icons glyphs (the theme ships
    // the BI webfont). Unknown handles fall back to a neutral badge icon.
    $iconMap = [
        'icon-return' => 'bi-arrow-counterclockwise',
        'icon-shipping' => 'bi-truck',
        'icon-headset' => 'bi-headset',
        'icon-sealCheck' => 'bi-patch-check',
    ];
@endphp
@if($items)
    <section class="iconbox bg-light py-4 my-5">
        <div class="container">
            <div class="row g-4 text-center">
                @foreach($items as $item)
                    @php $glyph = $iconMap[$item['icon'] ?? ''] ?? 'bi-patch-check'; @endphp
                    <div class="col-6 col-lg-3">
                        <i class="bi {{ $glyph }} iconbox__icon fs-2 mb-2 d-inline-block"></i>
                        <h3 class="h6 mb-1">{{ $item['heading'] ?? '' }}</h3>
                        <p class="small text-muted mb-0">{{ $item['text'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
