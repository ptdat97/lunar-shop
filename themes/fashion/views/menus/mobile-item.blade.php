{{-- Mobile off-canvas item. Leaf → link; has children → accordion section.
     $uid is unique per root item (provided by MenuRenderer::renderMobile). --}}
@if($item->children->isEmpty())
    <a class="d-block py-2 text-dark text-decoration-none border-bottom" href="{{ $item->resolveUrl() }}">
        {{ $item->label }}
    </a>
@else
    <div class="accordion-item border-0">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed px-0 py-2 shadow-none" type="button"
                    data-bs-toggle="collapse" data-bs-target="#m-{{ $uid }}">
                {{ $item->label }}
            </button>
        </h2>
        <div id="m-{{ $uid }}" class="accordion-collapse collapse"
             data-bs-parent="#mobileMenuAccordion">
            <div class="accordion-body px-2 py-1">
                @foreach($item->children as $child)
                    @if($child->children->isEmpty())
                        <a class="d-block py-2 text-dark text-decoration-none" href="{{ $child->resolveUrl() }}">
                            {{ $child->label }}
                        </a>
                    @else
                        <div class="fw-semibold text-uppercase small text-muted mt-2">{{ $child->label }}</div>
                        @foreach($child->children as $link)
                            <a class="d-block py-1 ps-2 text-dark text-decoration-none" href="{{ $link->resolveUrl() }}">
                                {{ $link->label }}
                            </a>
                        @endforeach
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
