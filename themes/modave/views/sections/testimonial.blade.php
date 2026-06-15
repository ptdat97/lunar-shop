@php
    use Modules\SectionBuilder\Support\SectionSchemas;
    $items = $settings['items'] ?? SectionSchemas::defaults('testimonial')['items'];
    $img = function (?string $path): string {
        if (! $path) return '';
        return \Illuminate\Support\Str::startsWith($path, ['/', 'http'])
            ? $path
            : \Illuminate\Support\Facades\Storage::disk('media')->url($path);
    };
@endphp

<section>
    <div class="container">
        <div class="heading-section text-center wow fadeInUp">
            <h3 class="heading">{{ $settings['heading'] ?? 'Customer Say!' }}</h3>
            <p class="subheading">{{ $settings['subheading'] ?? 'Our customers adore our products, and we constantly aim to delight them.' }}</p>
        </div>
        <div dir="ltr" class="swiper tf-sw-testimonial wow fadeInUp" data-wow-delay="0.1s" data-preview="2" data-tablet="1.3" data-mobile="1" data-space-lg="30" data-space-md="30" data-space="15" data-pagination="1" data-pagination-md="1" data-pagination-lg="1">
            <div class="swiper-wrapper">
                @foreach ($items as $item)
                    <div class="swiper-slide">
                        <div class="testimonial-item hover-img">
                            <div class="img-style">
                                <img data-src="{{ $img($item['image'] ?? '') }}" src="{{ $img($item['image'] ?? '') }}" alt="img-testimonial">
                                <a href="#quickView" data-bs-toggle="modal" class="box-icon hover-tooltip center">
                                    <span class="icon icon-eye"></span>
                                    <span class="tooltip">Quick View</span>
                                </a>
                            </div>
                            <div class="content">
                                <div class="content-top">
                                    <div class="list-star-default">
                                        @for ($i = 0; $i < (int) ($item['rating'] ?? 5); $i++)
                                            <i class="icon icon-star"></i>
                                        @endfor
                                    </div>
                                    <p class="text-secondary">"{{ $item['text'] ?? '' }}"</p>
                                    <div class="box-author">
                                        <div class="text-title author">{{ $item['author'] ?? '' }}</div>
                                        <svg class="icon" width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M6.875 11.6255L8.75 13.5005L13.125 9.12549" stroke="#3DAB25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M10 18.5005C14.1421 18.5005 17.5 15.1426 17.5 11.0005C17.5 6.85835 14.1421 3.50049 10 3.50049C5.85786 3.50049 2.5 6.85835 2.5 11.0005C2.5 15.1426 5.85786 18.5005 10 18.5005Z" stroke="#3DAB25" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="box-avt">
                                    <div class="avatar avt-60 round">
                                        <img src="{{ $img($item['avatar'] ?? '') }}" alt="avt">
                                    </div>
                                    <div class="box-price">
                                        <p class="text-title text-line-clamp-1">{{ $item['product_name'] ?? '' }}</p>
                                        <div class="text-button price">{{ $item['product_price'] ?? '' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="sw-pagination-testimonial sw-dots type-circle d-flex justify-content-center"></div>
        </div>
    </div>
</section>
