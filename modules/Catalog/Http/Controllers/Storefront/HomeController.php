<?php

namespace Modules\Catalog\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\SectionBuilder\Services\SectionRenderer;

class HomeController extends Controller
{
    public function __construct(
        protected SectionRenderer $sections,
    ) {}

    /**
     * Storefront home — rendered from the DB-driven section list.
     * The theme only renders; data comes from each section's provider.
     */
    public function __invoke(): View
    {
        return view('theme::pages.home', [
            'sections' => $this->sections->render('home'),
        ]);
    }
}
