@php
    $columns = $item->children->where('type', 'mega-column');
    $banners = $item->children->where('type', 'banner');
    // Uploaded banners store a relative path on the "media" disk; template
    // defaults are absolute "/themes/..." paths or full URLs.
    $bannerUrl = function (?string $img): string {
        if (! $img) return '';
        return \Illuminate\Support\Str::startsWith($img, ['/', 'http'])
            ? $img
            : \Illuminate\Support\Facades\Storage::disk('media')->url($img);
    };
@endphp

<li class="menu-item">
    <a href="{{ $item->resolveUrl() }}" class="item-link">{{ $item->label }}<i class="icon icon-arrow-down"></i></a>
    <div class="sub-menu mega-menu">
        <div class="container">
            <div class="row">
                @foreach ($columns as $column)
                    <div class="col-lg-2">
                        <div class="mega-menu-item">
                            <div class="menu-heading">{{ $column->label }}</div>
                            <ul class="menu-list">
                                @foreach ($column->children as $link)
                                    <li>
                                        <a href="{{ $link->resolveUrl() }}" target="{{ $link->target }}" class="menu-link-text">
                                            {{ $link->label }}
                                            @if ($link->badge)<span class="menu-badge">{{ $link->badge }}</span>@endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach

                @foreach ($banners as $banner)
                    <div class="col-lg-4">
                        <a href="{{ $banner->resolveUrl() }}" class="mega-menu-banner hover-img d-block">
                            <div class="img-style">
                                <img class="lazyload" data-src="{{ $bannerUrl($banner->image) }}" src="{{ $bannerUrl($banner->image) }}" alt="{{ $banner->label }}">
                            </div>
                            @if ($banner->label)
                                <div class="banner-content mt_8"><span class="menu-link-text">{{ $banner->label }}</span></div>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</li>
