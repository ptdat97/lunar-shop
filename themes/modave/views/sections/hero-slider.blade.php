@php
    use Modules\SectionBuilder\Support\SectionSchemas;
    $slides = $settings['slides'] ?? SectionSchemas::defaults('hero-slider')['slides'];
    // Uploaded images are stored as a relative path on the "media" disk;
    // template defaults are absolute "/themes/..." paths or full URLs.
    $slideUrl = function (?string $img): string {
        if (! $img) return '';
        return \Illuminate\Support\Str::startsWith($img, ['/', 'http'])
            ? $img
            : \Illuminate\Support\Facades\Storage::disk('media')->url($img);
    };
@endphp

<section class="tf-slideshow slider-style2 slider-effect-fade">
    <div dir="ltr" class="swiper tf-sw-slideshow" data-preview="1" data-tablet="1" data-mobile="1" data-centered="false" data-space="0" data-space-mb="0" data-loop="true" data-auto-play="false">
        <div class="swiper-wrapper">
            @foreach ($slides as $slide)
                <div class="swiper-slide">
                    <div class="wrap-slider">
                        <img src="{{ $slideUrl($slide['image'] ?? '') }}" alt="fashion-slideshow">
                        <div class="box-content">
                            <div class="container">
                                <div class="row">
                                    <div class="col-xxl-6 col-md-7 col-sm-10">
                                        <div class="content-slider card-box style-2">
                                            <div class="box-title-slider">
                                                <div class="fade-item fade-item-1 heading title-display">{!! nl2br(e($slide['title'] ?? '')) !!}</div>
                                                <p class="fade-item fade-item-2 body-text-1">{{ $slide['subtitle'] ?? '' }}</p>
                                            </div>
                                            @if (!empty($slide['button_text']))
                                                <div class="fade-item fade-item-3 box-btn-slider">
                                                    <a href="{{ $slide['button_url'] ?? '#' }}" class="tf-btn btn-fill"><span class="text">{{ $slide['button_text'] }}</span><i class="icon icon-arrowUpRight"></i></a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="wrap-pagination">
        <div class="container">
            <div class="sw-dots sw-pagination-slider type-circle justify-content-center"></div>
        </div>
    </div>
</section>
