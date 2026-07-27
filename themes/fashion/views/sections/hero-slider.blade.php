@php $slides = $settings['slides'] ?? []; @endphp
@if($slides)
    <section class="hero-slider">
        <div class="swiper" data-hero-swiper>
            <div class="swiper-wrapper">
                @foreach($slides as $i => $slide)
                    @php $slideImage = media_url($slide['image'] ?? null); @endphp
                    <div class="swiper-slide"
                         @if($slideImage) style="background-image:url('{{ $slideImage }}');" @endif>
                        <div class="hero-slide__content">
                            @if(!empty($slide['kicker']))
                                <span class="hero-slide__kicker">{{ $slide['kicker'] }}</span>
                            @endif
                            @if(!empty($slide['title']))
                                <h2 class="hero-slide__title">{{ $slide['title'] }}</h2>
                            @endif
                            @if(!empty($slide['subtitle']))
                                <p class="hero-slide__subtitle">{{ $slide['subtitle'] }}</p>
                            @endif
                            @if(!empty($slide['button_text']))
                                <a href="{{ $slide['button_url'] ?? '#' }}" class="btn-editorial btn-editorial--light hero-slide__cta">
                                    {{ $slide['button_text'] }}
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            @endif
                        </div>
                        {{-- Slide index, editorial detail (01 / 03) --}}
                        <span class="hero-slide__index" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>
    {{-- Swiper init lives in enhance/sliders.js — see note in promotion-slider. --}}
@endif
