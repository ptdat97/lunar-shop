@php $collections = $collections ?? collect(); @endphp
@if($collections->isNotEmpty())
    <section class="container my-5">
        @if(!empty($settings['heading']))
            <h2 class="h4 text-center mb-4">{{ $settings['heading'] }}</h2>
        @endif
        <div class="row g-3">
            @foreach($collections as $collection)
                @php
                    $slug = $collection->defaultUrl?->slug;
                    $url = $slug ? route('storefront.collection', $slug) : '#';
                    $thumb = $collection->thumbnail;
                    $image = null;
                    if ($thumb) {
                        try { $image = $thumb->getUrl(); } catch (\Throwable $e) { $image = null; }
                    }
                @endphp
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="{{ $url }}" class="d-block text-center text-decoration-none text-dark">
                        <div class="ratio ratio-1x1 rounded-circle overflow-hidden bg-light mb-2">
                            @if($image)
                                <img src="{{ $image }}" alt="{{ $collection->translateAttribute('name') }}"
                                     class="w-100 h-100" style="object-fit:cover" loading="lazy">
                            @endif
                        </div>
                        <span class="small fw-semibold">{{ $collection->translateAttribute('name') }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    </section>
@endif
