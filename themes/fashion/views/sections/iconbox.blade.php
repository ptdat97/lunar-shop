@php $items = $settings['items'] ?? []; @endphp
@if($items)
    <section class="bg-light py-4 my-5">
        <div class="container">
            <div class="row g-4 text-center">
                @foreach($items as $item)
                    <div class="col-6 col-lg-3">
                        <h3 class="h6 mb-1">{{ $item['heading'] ?? '' }}</h3>
                        <p class="small text-muted mb-0">{{ $item['text'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
