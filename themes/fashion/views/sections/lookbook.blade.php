@php $slides = $settings['slides'] ?? []; @endphp
@if($slides)
    <section class="lookbook container my-5">
        @if(!empty($settings['heading']))
            <h2 class="h4 text-center mb-1">{{ $settings['heading'] }}</h2>
        @endif
        @if(!empty($settings['subheading']))
            <p class="text-center text-muted mb-4">{{ $settings['subheading'] }}</p>
        @endif

        <div class="row g-4">
            @foreach($slides as $slide)
                @php
                    $banner = media_url($slide['banner'] ?? null);
                    $pinImage = media_url($slide['pin_image'] ?? null);
                    // Pin placement preset → top/left percentages. Falls back to centre.
                    $positions = [
                        'position3' => ['top' => '32%', 'left' => '28%'],
                        'position5' => ['top' => '60%', 'left' => '62%'],
                    ];
                    $pos = $positions[$slide['position'] ?? ''] ?? ['top' => '50%', 'left' => '50%'];
                @endphp
                @if($banner)
                    <div class="col-12 col-md-6">
                        <div class="lookbook__frame position-relative overflow-hidden rounded">
                            <img src="{{ $banner }}" alt="{{ $slide['pin_title'] ?? 'Lookbook' }}"
                                 class="w-100 h-100" style="object-fit:cover" loading="lazy">

                            @if(!empty($slide['pin_title']) || $pinImage)
                                <div class="lookbook__pin position-absolute"
                                     style="top: {{ $pos['top'] }}; left: {{ $pos['left'] }};">
                                    <button type="button" class="lookbook__dot" aria-label="Shop this item"></button>
                                    <a href="{{ $slide['pin_url'] ?? '#' }}"
                                       class="lookbook__card text-decoration-none text-dark d-flex align-items-center gap-2">
                                        @if($pinImage)
                                            <img src="{{ $pinImage }}" alt="{{ $slide['pin_title'] ?? '' }}"
                                                 class="lookbook__thumb" loading="lazy">
                                        @endif
                                        <span class="lookbook__meta">
                                            @if(!empty($slide['pin_title']))
                                                <span class="d-block small fw-semibold">{{ $slide['pin_title'] }}</span>
                                            @endif
                                            @if(!empty($slide['pin_price']))
                                                <span class="d-block small text-muted">{{ $slide['pin_price'] }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endif
