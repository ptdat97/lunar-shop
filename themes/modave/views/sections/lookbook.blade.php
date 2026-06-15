@php
    use Modules\SectionBuilder\Support\SectionSchemas;
    $slides = $settings['slides'] ?? SectionSchemas::defaults('lookbook')['slides'];
    $img = function (?string $path): string {
        if (! $path) return '';
        return \Illuminate\Support\Str::startsWith($path, ['/', 'http'])
            ? $path
            : \Illuminate\Support\Facades\Storage::disk('media')->url($path);
    };
@endphp

<section class="flat-spacing pt-0">
    <div class="swiper tf-sw-lookbook sw-lookbook-wrap" dir="ltr" data-preview="3" data-tablet="2" data-mobile="1" data-space-lg="0" data-space-md="0" data-space="0" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
        <div class="swiper-wrapper">
            @foreach ($slides as $slide)
                <div class="swiper-slide">
                    <div class="hover-img cls-lookbook">
                        <a href="{{ $slide['pin_url'] ?? '#' }}" class="img-style rounded-0">
                            <img class="lazyload" data-src="{{ $img($slide['banner'] ?? '') }}" src="{{ $img($slide['banner'] ?? '') }}" alt="banner-cls">
                        </a>
                        <div class="lookbook-item {{ $slide['position'] ?? '' }}">
                            <div class="dropup-center dropup">
                                <div role="dialog" class="tf-pin-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span></span>
                                </div>
                                <div class="dropdown-menu">
                                    <div class="loobook-product">
                                        <div class="img-style">
                                            <img src="{{ $img($slide['pin_image'] ?? '') }}" alt="img">
                                        </div>
                                        <div class="content">
                                            <div class="info">
                                                <a href="{{ $slide['pin_url'] ?? '#' }}" class="text-title text-line-clamp-1 link">{{ $slide['pin_title'] ?? '' }}</a>
                                                <div class="price text-button">{{ $slide['pin_price'] ?? '' }}</div>
                                            </div>
                                            <a href="{{ $slide['pin_url'] ?? '#' }}" class="btn-lookbook btn-line">Quick View</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="sw-pagination-lookbook sw-dots type-circle justify-content-center"></div>
    </div>
</section>
