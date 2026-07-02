@php $collections = $collections ?? collect(); @endphp
@if($collections->isNotEmpty())
    <section class="container my-5 category-section">
        <div class="section-head">
            @if(!empty($settings['kicker']))
                <span class="eyebrow">{{ $settings['kicker'] }}</span>
            @endif
            @if(!empty($settings['heading']))
                <h2 class="display-heading">{{ $settings['heading'] }}</h2>
            @endif
        </div>

        {{-- Asymmetric editorial grid: the first collection takes a wider,
             feature tile; the rest flow in a portrait grid beside it. --}}
        <div class="category-grid">
            @foreach($collections as $i => $collection)
                @php
                    $slug = $collection->defaultUrl?->slug;
                    $url = $slug ? route('storefront.collection', $slug) : '#';
                    // $collectionImage closure injected by the Media view composer.
                    $image = $collectionImage($collection, 'medium');
                    $name = $collection->translateAttribute('name');
                @endphp
                <a href="{{ $url }}" class="category-grid__item {{ $i === 0 ? 'category-grid__item--feature' : '' }}">
                    @if($image)
                        <img src="{{ $image }}" alt="{{ $name }}" loading="lazy">
                    @endif
                    <span class="category-grid__label">
                        <span class="category-grid__name">{{ $name }}</span>
                        <span class="category-grid__shop">Shop now <i class="bi bi-arrow-right"></i></span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
