{{-- Mobile off-canvas item. Leaf → link; has children → accordion section.
     $uid is unique per root item (provided by MenuRenderer::renderMobile).
     Styling lives in css/features/_mobile-menu.scss (.mobile-menu__*). --}}
@php
    $isCurrent = fn ($url) => $url && request()->fullUrlIs(url($url).'*');
@endphp

@if($item->children->isEmpty())
    @php($url = $item->resolveUrl())
    <a class="mobile-menu__link mobile-menu__link--root {{ $isCurrent($url) ? 'is-current' : '' }}"
       href="{{ $url }}" @if($isCurrent($url)) aria-current="page" @endif>
        {{ $item->label }}
    </a>
@else
    <div class="mobile-menu__section">
        <h2 class="mobile-menu__heading">
            <button class="mobile-menu__toggle collapsed" type="button"
                    data-bs-toggle="collapse" data-bs-target="#m-{{ $uid }}"
                    aria-expanded="false" aria-controls="m-{{ $uid }}">
                <span>{{ $item->label }}</span>
                <i class="bi bi-chevron-down mobile-menu__chevron" aria-hidden="true"></i>
            </button>
        </h2>
        <div id="m-{{ $uid }}" class="collapse" data-bs-parent="#mobileMenuAccordion">
            <div class="mobile-menu__panel">
                {{-- "View all" keeps the parent reachable — its own button only toggles. --}}
                @php($parentUrl = $item->resolveUrl())
                @if($parentUrl)
                    <a class="mobile-menu__view-all" href="{{ $parentUrl }}">
                        {{ __('storefront.nav.view_all_in', ['label' => $item->label]) }}
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif

                @foreach($item->children as $child)
                    @if($child->children->isEmpty())
                        @php($childUrl = $child->resolveUrl())
                        <a class="mobile-menu__link {{ $isCurrent($childUrl) ? 'is-current' : '' }}"
                           href="{{ $childUrl }}" @if($isCurrent($childUrl)) aria-current="page" @endif>
                            {{ $child->label }}
                        </a>
                    @else
                        {{-- Third level: a labelled group of links, not another accordion —
                             nesting collapses this deep costs more taps than it saves. --}}
                        <div class="mobile-menu__group">
                            <div class="mobile-menu__group-label">{{ $child->label }}</div>
                            @foreach($child->children as $link)
                                @php($linkUrl = $link->resolveUrl())
                                <a class="mobile-menu__link mobile-menu__link--nested {{ $isCurrent($linkUrl) ? 'is-current' : '' }}"
                                   href="{{ $linkUrl }}" @if($isCurrent($linkUrl)) aria-current="page" @endif>
                                    {{ $link->label }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
