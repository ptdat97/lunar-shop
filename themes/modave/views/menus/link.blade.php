@php
    // A link renders as a top-level <li> when it has no parent, or as a plain
    // <li><a> list item when nested inside a column/dropdown.
    $isNested = $item->parent_id !== null;
    $href = $item->resolveUrl();
@endphp

@if ($isNested)
    <li>
        <a href="{{ $href }}" target="{{ $item->target }}" class="menu-link-text">
            {{ $item->label }}
            @if ($item->badge)<span class="menu-badge">{{ $item->badge }}</span>@endif
        </a>
    </li>
@else
    <li class="menu-item">
        <a href="{{ $href }}" target="{{ $item->target }}" class="item-link">
            {{ $item->label }}
            @if ($item->badge)<span class="menu-badge">{{ $item->badge }}</span>@endif
        </a>
    </li>
@endif
