{{-- Search panel (autocomplete). Hidden by default; the search icon toggles it
     open (enhance/search-panel.js). The form is a real GET to /search so search
     works with no JS (the icon is a plain link → /search as fallback). The
     suggestions list is fetched from /api/v1/search/suggest as the user types. --}}
<div class="search-panel border-top" id="searchPanel" data-search-panel hidden>
    <div class="container py-3">
        <form action="{{ route('storefront.search') }}" method="GET" role="search" data-search-form>
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control border-start-0 ps-0"
                       placeholder="{{ __('storefront.search.placeholder') }}"
                       autocomplete="off" data-search-input
                       aria-label="{{ __('storefront.nav.search') }}"
                       aria-controls="searchSuggestions">
                <button class="btn btn-dark" type="submit">{{ __('storefront.nav.search') }}</button>
                <button class="btn btn-outline-secondary border-start-0" type="button"
                        data-search-close aria-label="{{ __('storefront.common.cancel') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </form>

        {{-- Suggestion results (filled by JS). Each is a link to /search?q=… so
             clicking works even before the SPA-style grid loads. --}}
        <ul class="search-suggestions list-unstyled mt-2 mb-0" id="searchSuggestions"
            data-search-suggestions role="listbox" hidden></ul>
    </div>
</div>
