{{-- Simple dropdown: a top-level label with a flat list of child links. --}}
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="{{ $item->resolveUrl() }}"
       role="button" data-bs-toggle="dropdown" aria-expanded="false">
        {{ $item->label }}
    </a>
    <ul class="dropdown-menu">
        @foreach($item->children as $child)
            <li>
                <a class="dropdown-item" href="{{ $child->resolveUrl() }}"
                   @if($child->target) target="{{ $child->target }}" @endif>
                    {{ $child->label }}
                </a>
            </li>
        @endforeach
    </ul>
</li>
