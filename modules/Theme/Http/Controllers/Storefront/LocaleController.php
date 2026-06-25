<?php

namespace Modules\Theme\Http\Controllers\Storefront;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Theme\Services\LocaleService;

/**
 * Storefront language switch. Persists the chosen locale to the session and
 * returns the visitor to where they were (no business logic in views).
 */
class LocaleController extends Controller
{
    public function __construct(protected LocaleService $locales)
    {
    }

    public function switch(Request $request, string $locale): RedirectResponse
    {
        $this->locales->store($request, $locale);

        // Back to the referring page, or home as a safe fallback.
        return redirect()->to($request->headers->get('referer') ?: '/');
    }
}
