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
    <section class="iconbox">
        <div class="container">
            <div class="iconbox__row">
                @foreach($items as $item)
                    @php $glyph = $iconMap[$item['icon'] ?? ''] ?? 'bi-patch-check'; @endphp
                    <div class="iconbox__item">
                        <i class="bi {{ $glyph }} iconbox__icon" aria-hidden="true"></i>
                        <div>
                            <h3 class="iconbox__heading">{{ $item['heading'] ?? '' }}</h3>
                            <p class="iconbox__text">{{ $item['text'] ?? '' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
