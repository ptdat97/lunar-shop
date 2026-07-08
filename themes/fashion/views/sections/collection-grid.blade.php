@php $items = $items ?? collect(); @endphp
@if($items->isNotEmpty())
    <section class="container my-5 collection-section">
        <div class="section-head">
            @if(!empty($settings['kicker']))
                <span class="eyebrow">{{ $settings['kicker'] }}</span>
            @endif
            @if(!empty($settings['heading']))
                <h2 class="display-heading">{{ $settings['heading'] }}</h2>
            @endif
        </div>

        {{-- Asymmetric editorial grid: the first item takes a wider feature
             tile; the rest flow in a portrait grid beside it. Items (with their
             resolved image/name/url) come from the collection-grid provider. --}}
        <div class="collection-grid">
            @foreach($items as $i => $item)
                <a href="{{ $item['url'] }}" class="collection-grid__item {{ $i === 0 ? 'collection-grid__item--feature' : '' }}">
                    @if(!empty($item['image']))
                        <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}" loading="lazy">
                    @endif
                    <span class="collection-grid__label">
                        <span class="collection-grid__name">{{ $item['name'] }}</span>
                        <span class="collection-grid__shop">Shop now <i class="bi bi-arrow-right"></i></span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
