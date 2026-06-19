@php $slides = $settings['slides'] ?? []; @endphp
@if($slides)
    <section class="hero-slider">
        <div class="swiper" data-hero-swiper>
            <div class="swiper-wrapper">
                @foreach($slides as $slide)
                    <div class="swiper-slide d-flex align-items-center text-white"
                         style="background-image: linear-gradient(rgba(0,0,0,.25), rgba(0,0,0,.25)), url('{{ $slide['image'] ?? '' }}');">
                        <div class="container">
                            <div class="col-12 col-md-7">
                                @if(!empty($slide['title']))
                                    <h2 class="display-5 fw-bold">{{ $slide['title'] }}</h2>
                                @endif
                                @if(!empty($slide['subtitle']))
                                    <p class="lead">{{ $slide['subtitle'] }}</p>
                                @endif
                                @if(!empty($slide['button_text']))
                                    <a href="{{ $slide['button_url'] ?? '#' }}" class="btn btn-light px-4">
                                        {{ $slide['button_text'] }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>

    @once
        @push('scripts')
            <script>
                document.querySelectorAll('[data-hero-swiper]').forEach((el) => {
                    new Swiper(el, {
                        loop: true,
                        autoplay: { delay: 5000 },
                        pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
                    });
                });
            </script>
        @endpush
    @endonce
@endif
