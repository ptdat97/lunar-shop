{{-- Mega menu: a top-level label opening a full-width panel of columns.
     Children are `mega-column` items (and a `banner`), rendered inline here. --}}
<li class="nav-item dropdown position-static">
    <a class="nav-link dropdown-toggle" href="{{ $item->resolveUrl() }}"
       role="button" data-bs-toggle="dropdown" aria-expanded="false">
        {{ $item->label }}
    </a>
    <div class="dropdown-menu w-100 mt-0 border-0 shadow-sm">
        <div class="container">
            <div class="row g-4 py-3">
                @foreach($item->children as $child)
                    @if($child->type === 'banner')
                        <div class="col-12 col-lg-3">
                            <a href="{{ $child->resolveUrl() }}" class="d-block text-decoration-none">
                                @if($child->image)
                                    <img src="{{ $child->image }}" alt="{{ $child->label }}"
                                         class="img-fluid rounded mb-2"
                                         onerror="this.style.display='none'">
                                @endif
                                <span class="fw-semibold text-dark">{{ $child->label }}</span>
                            </a>
                        </div>
                    @else
                        <div class="col-6 col-lg-3">
                            <h6 class="text-uppercase small text-muted mb-2">{{ $child->label }}</h6>
                            <ul class="list-unstyled d-flex flex-column gap-2 mb-0">
                                @foreach($child->children as $link)
                                    <li>
                                        <a class="text-decoration-none text-dark" href="{{ $link->resolveUrl() }}">
                                            {{ $link->label }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</li>
