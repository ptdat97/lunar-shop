@php
    use Modules\SectionBuilder\Support\SectionSchemas;
    $items = $settings['items'] ?? SectionSchemas::defaults('iconbox')['items'];
@endphp

<section class="flat-spacing line-bottom-container">
    <div class="container">
        <div dir="ltr" class="swiper tf-sw-iconbox" data-preview="4" data-tablet="3" data-mobile-sm="2" data-mobile="1" data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1" data-pagination-sm="2" data-pagination-md="3" data-pagination-lg="4">
            <div class="swiper-wrapper">
                @foreach ($items as $item)
                    <div class="swiper-slide">
                        <div class="tf-icon-box style-2">
                            <div class="icon-box"><span class="icon {{ $item['icon'] ?? 'icon-sealCheck' }}"></span></div>
                            <div class="content">
                                <h6>{{ $item['heading'] ?? '' }}</h6>
                                <p class="text-secondary">{{ $item['text'] ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="sw-pagination-iconbox sw-dots type-circle justify-content-center"></div>
        </div>
    </div>
</section>
