{{-- Language switcher dropdown. $storefrontLocales (code => label),
     $currentLocale and $showLanguageSwitcher are injected by the Theme view
     composer (standards §7). Hidden when the admin disabled it or only one
     locale is enabled. Each option is a real GET link (no-JS friendly). --}}
@if(($showLanguageSwitcher ?? false) && ($storefrontLocales ?? []))
    <div class="dropdown" data-language-switcher>
        <button class="btn btn-link text-dark text-decoration-none p-0 d-flex align-items-center gap-1 dropdown-toggle"
                type="button" data-bs-toggle="dropdown" aria-expanded="false"
                aria-label="{{ __('storefront.language.label') }}">
            <i class="bi bi-globe2 fs-5"></i>
            <span class="small text-uppercase d-none d-sm-inline">{{ $currentLocale }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach($storefrontLocales as $code => $label)
                <li>
                    <a class="dropdown-item d-flex align-items-center justify-content-between gap-3 {{ $code === $currentLocale ? 'active' : '' }}"
                       href="{{ route('storefront.locale.switch', $code) }}">
                        {{ $label }}
                        @if($code === $currentLocale)<i class="bi bi-check2"></i>@endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
