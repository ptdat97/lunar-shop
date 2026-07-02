@php $items = $settings['items'] ?? []; @endphp
@if($items)
    <section class="testimonials">
        <div class="container">
            <div class="section-head section-head--center">
                @if(!empty($settings['kicker']))
                    <span class="eyebrow">{{ $settings['kicker'] }}</span>
                @endif
                @if(!empty($settings['heading']))
                    <h2 class="display-heading">{{ $settings['heading'] }}</h2>
                @endif
                @if(!empty($settings['subheading']))
                    <p class="section-head__intro mx-auto">{{ $settings['subheading'] }}</p>
                @endif
            </div>

            {{-- Swiper carousel of review cards; falls back to a static row with
                 no JS (every slide is real HTML). Init lives in enhance/sliders.js. --}}
            <div class="swiper testimonials__swiper" data-testimonial-swiper>
                <div class="swiper-wrapper">
                    @foreach($items as $item)
                        @php $rating = (int) ($item['rating'] ?? 5); @endphp
                        <div class="swiper-slide h-auto">
                            <figure class="testimonial-card h-100 d-flex flex-column">
                                @if($rating > 0)
                                    <div class="testimonial-card__stars text-warning mb-2" aria-label="{{ $rating }}/5">
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="bi {{ $i <= $rating ? 'bi-star-fill' : 'bi-star' }}"></i>
                                        @endfor
                                    </div>
                                @endif

                                @if(!empty($item['text']))
                                    <blockquote class="testimonial-card__text mb-3 flex-grow-1">
                                        {{ $item['text'] }}
                                    </blockquote>
                                @endif

                                <figcaption class="d-flex align-items-center gap-3 mt-auto">
                                    @if(!empty($item['avatar']))
                                        <img src="{{ $item['avatar'] }}" alt="{{ $item['author'] ?? '' }}"
                                             class="testimonial-card__avatar" width="48" height="48"
                                             loading="lazy">
                                    @endif
                                    <span>
                                        @if(!empty($item['author']))
                                            <span class="d-block fw-semibold">{{ $item['author'] }}</span>
                                        @endif
                                        @if(!empty($item['product_name']))
                                            <span class="d-block small text-muted">
                                                {{ $item['product_name'] }}
                                                @if(!empty($item['product_price'])) · {{ $item['product_price'] }} @endif
                                            </span>
                                        @endif
                                    </span>
                                </figcaption>
                            </figure>
                        </div>
                    @endforeach
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </section>
@endif
