<?php

namespace Modules\CMS\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\CMS\Services\CMSService;

class PageController extends Controller
{
    public function __construct(
        protected CMSService $cms,
    ) {}

    /**
     * Show a CMS page on the storefront.
     */
    public function show(string $slug): View
    {
        $page = $this->cms->page($slug);

        abort_unless($page, 404);

        return view('theme::pages.page', ['page' => $page]);
    }
}