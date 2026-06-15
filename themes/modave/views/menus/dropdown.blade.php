<li class="menu-item position-relative">
    <a href="{{ $item->resolveUrl() }}" class="item-link">{{ $item->label }}<i class="icon icon-arrow-down"></i></a>
    <div class="sub-menu submenu-default">
        <ul class="menu-list">
            @foreach ($item->children as $child)
                <li>
                    <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}" class="menu-link-text">{{ $child->label }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</li>
