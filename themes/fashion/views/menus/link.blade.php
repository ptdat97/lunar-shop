{{-- A single nav link. Used both at top level and inside mega/dropdown columns. --}}
<li class="nav-item">
    <a class="nav-link p-0 d-inline-flex align-items-center gap-1"
       href="{{ $item->resolveUrl() }}"
       @if($item->target) target="{{ $item->target }}" @endif>
        {{ $item->label }}
        @if($item->badge)
            <span class="badge bg-danger text-uppercase">{{ $item->badge }}</span>
        @endif
    </a>
</li>
