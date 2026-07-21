<?php

namespace Modules\Customer\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Customer\Services\CountryService;

class AuthPageController extends Controller
{
    public function __construct(
        protected CountryService $countries,
    ) {}

    /**
     * Login page (redirects home if already authenticated).
     */
    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('storefront.account');
        }

        return view('theme::pages.login');
    }

    /**
     * Register page.
     */
    public function register(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('storefront.account');
        }

        return view('theme::pages.register');
    }

    /**
     * Account page (requires login).
     */
    public function account(): View|RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('storefront.login');
        }

        return view('theme::pages.account', [
            'user' => Auth::user(),
            'countries' => $this->countries->forSelect(),
        ]);
    }
}
