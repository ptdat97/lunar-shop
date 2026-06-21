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
                    $banner = $slide['banner'] ?? '';
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

                            @if(!empty($slide['pin_title']) || !empty($slide['pin_image']))
                                <div class="lookbook__pin position-absolute"
                                     style="top: {{ $pos['top'] }}; left: {{ $pos['left'] }};">
                                    <button type="button" class="lookbook__dot" aria-label="Shop this item"></button>
                                    <a href="{{ $slide['pin_url'] ?? '#' }}"
                                       class="lookbook__card text-decoration-none text-dark d-flex align-items-center gap-2">
                                        @if(!empty($slide['pin_image']))
                                            <img src="{{ $slide['pin_image'] }}" alt="{{ $slide['pin_title'] ?? '' }}"
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

    @once
        @push('head')
            <style>
                .lookbook__frame { aspect-ratio: 4 / 5; background: #f4f4f4; }
                .lookbook__pin { transform: translate(-50%, -50%); }
                .lookbook__dot {
                    width: 28px; height: 28px; border: none; border-radius: 50%;
                    background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.25); cursor: pointer;
                    position: relative;
                }
                .lookbook__dot::before {
                    content: '+'; position: absolute; inset: 0; display: flex;
                    align-items: center; justify-content: center; font-size: 18px; line-height: 1; color: #111;
                }
                .lookbook__card {
                    position: absolute; top: 50%; left: calc(100% + 10px); transform: translateY(-50%);
                    width: max-content; max-width: 220px; padding: 8px; background: #fff; border-radius: 8px;
                    box-shadow: 0 4px 16px rgba(0,0,0,.18); opacity: 0; visibility: hidden;
                    transition: opacity .2s ease, visibility .2s ease;
                }
                .lookbook__pin:hover .lookbook__card { opacity: 1; visibility: visible; }
                .lookbook__thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; flex: 0 0 auto; }
            </style>
        @endpush
    @endonce
@endif
