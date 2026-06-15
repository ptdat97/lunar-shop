<div class="footer-col-block">
    <div class="footer-heading text-button footer-heading-mobile">
        {{ $item->label }}
    </div>
    <div class="tf-collapse-content">
        <ul class="footer-menu-list">
            @foreach ($item->children as $link)
                <li class="text-caption-1">
                    <a href="{{ $link->resolveUrl() }}" target="{{ $link->target }}" class="footer-menu_item">{{ $link->label }}</a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
