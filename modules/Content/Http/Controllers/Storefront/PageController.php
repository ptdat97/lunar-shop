<?php

namespace Modules\Content\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Content\Services\ContentService;

class PageController extends Controller
{
    public function __construct(
        protected ContentService $cms,
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