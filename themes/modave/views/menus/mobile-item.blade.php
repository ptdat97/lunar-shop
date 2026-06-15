@php
    // Flatten children into a list of sub-links for the mobile accordion.
    // mega → gather links from each column; dropdown/footer-column → direct links.
    $subLinks = collect();
    foreach ($item->children as $child) {
        if ($child->type === 'mega-column') {
            $subLinks = $subLinks->merge($child->children);
        } elseif ($child->type === 'banner') {
            // skip banners on mobile
            continue;
        } else {
            $subLinks->push($child);
        }
    }
    $hasSub = $subLinks->isNotEmpty();
    $panelId = 'mb-' . $uid;
@endphp

<li class="nav-mb-item">
    @if ($hasSub)
        <a href="#{{ $panelId }}" class="collapsed mb-menu-link" data-bs-toggle="collapse" aria-expanded="false" aria-controls="{{ $panelId }}">
            <span>{{ $item->label }}</span>
            <span class="btn-open-sub"></span>
        </a>
        <div id="{{ $panelId }}" class="collapse">
            <ul class="sub-nav-menu">
                @foreach ($subLinks as $link)
                    <li>
                        <a href="{{ $link->resolveUrl() }}" target="{{ $link->target }}" class="sub-nav-link">{{ $link->label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}" class="mb-menu-link">
            <span>{{ $item->label }}</span>
        </a>
    @endif
</li>
