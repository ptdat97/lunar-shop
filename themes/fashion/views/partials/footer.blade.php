{{-- $menus (MenuRenderer) injected by the Menu view composer (standards §7). --}}

<footer class="bg-dark text-white-50 pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <a class="navbar-brand text-white fw-bold text-uppercase d-block mb-3"
                   href="{{ route('storefront.home') }}">{{ config('app.name') }}</a>
                @if($address = $theme->get('contact.address'))
                    <p class="mb-1">{{ $address }}</p>
                @endif
                @if($email = $theme->get('contact.email'))
                    <p class="mb-1"><a href="mailto:{{ $email }}" class="text-white-50">{{ $email }}</a></p>
                @endif
                @if($phone = $theme->get('contact.phone'))
                    <p class="mb-0">{{ $phone }}</p>
                @endif
            </div>

            <div class="col-12 col-lg-8">
                <div class="row g-4">
                    {!! $menus->render('footer') !!}
                </div>
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <small>{{ $theme->get('copyright', '© '.date('Y').' '.config('app.name')) }}</small>
            <div class="d-flex gap-3">
                @foreach($theme->get('social', []) as $link)
                    <a href="{{ $link['url'] ?? '#' }}" class="text-white-50">{{ $link['icon'] ?? '' }}</a>
                @endforeach
            </div>
        </div>
    </div>
</footer>
