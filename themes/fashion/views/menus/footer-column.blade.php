{{-- A footer column: heading + list of child links. --}}
<div class="col-6 col-md-3">
    <h6 class="text-uppercase text-white small mb-3">{{ $item->label }}</h6>
    <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
        @foreach($item->children as $child)
            <li>
                <a class="text-white-50 text-decoration-none" href="{{ $child->resolveUrl() }}"
                   @if($child->target) target="{{ $child->target }}" @endif>
                    {{ $child->label }}
                </a>
            </li>
        @endforeach
    </ul>
</div>
