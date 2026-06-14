@php
    $heading = $settings['heading'] ?? 'Shop by categories';
    $cols = $collections ?? collect();
    // Modave grid uses fixed item1..itemN slot classes; fall back to demo art.
    $fallback = [
        '/themes/modave/images/collections/grid-cls/women-cls.jpg',
        '/themes/modave/images/collections/grid-cls/promotion-cls.jpg',
        '/themes/modave/images/collections/grid-cls/accessories-cls.jpg',
        '/themes/modave/images/collections/grid-cls/men-cls.jpg',
    ];
@endphp

<section class="flat-spacing">
    <div class="container">
        <div class="heading-section text-center wow fadeInUp">
            <h3 class="heading">{{ $heading }}</h3>
        </div>
        <div class="grid-cls grid-cls-v1">
            @foreach ($cols as $i => $collection)
                @php
                    $img = $collection->thumbnail?->getUrl('medium') ?? $fallback[$i % count($fallback)];
                    $name = $collection->translateAttribute('name');
                    $slug = $collection->defaultUrl?->slug;
                    $url = $slug ? route('storefront.collection', $slug) : '#';
                @endphp
                <div class="item{{ $i + 1 }} collection-position-2 hover-img">
                    <a href="{{ $url }}" class="img-style">
                        <img class="lazyload" data-src="{{ $img }}" src="{{ $img }}" alt="{{ $name }}">
                    </a>
                    <div class="content">
                        <a href="{{ $url }}" class="cls-btn"><h6 class="text">{{ $name }}</h6><i class="icon icon-arrowUpRight"></i></a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
