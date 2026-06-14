<?php

namespace Modules\Search\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SearchController extends Controller
{
    /**
     * Search results page. Renders the shell; the results island fetches
     * from /api/v1/search so the same query path serves web + app.
     */
    public function __invoke(Request $request): View
    {
        return view('theme::pages.search', [
            'query' => (string) $request->string('q', ''),
        ]);
    }
}
