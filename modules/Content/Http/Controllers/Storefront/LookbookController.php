<?php

namespace Modules\Content\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Content\Services\ContentService;

class LookbookController extends Controller
{
    public function __construct(
        protected ContentService $cms,
    ) {}

    /**
     * List of published lookbooks.
     */
    public function index(): View
    {
        return view('theme::pages.lookbooks', [
            'lookbooks' => $this->cms->lookbooks(),
        ]);
    }

    /**
     * A single lookbook: photo gallery on top, products at the bottom.
     */
    public function show(string $slug): View
    {
        $lookbook = $this->cms->lookbook($slug);

        abort_unless($lookbook, 404);

        return view('theme::pages.lookbook', ['lookbook' => $lookbook]);
    }
}
