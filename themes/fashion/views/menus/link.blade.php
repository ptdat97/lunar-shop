{{-- A single nav link. Used both at top level and inside mega/dropdown columns.
     Marks the current page so users know where they are (aria-current + style). --}}
@php
    $href = $item->resolveUrl();
    $isActive = $href && $href !== '#' && rtrim(url()->current(), '/') === rtrim($href, '/');
@endphp
<li class="nav-item">
    <a class="nav-link {{ $isActive ? 'is-active' : '' }}"
       href="{{ $href }}"
       @if($isActive) aria-current="page" @endif
       @if($item->target) target="{{ $item->target }}" @endif>
        {{ $item->label }}
        @if($item->badge)
            <span class="nav-link__badge">{{ $item->badge }}</span>
        @endif
    </a>
</li>
