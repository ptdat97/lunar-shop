<?php

namespace Modules\Collection\Http\Controllers\Storefront;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Collection\Services\CollectionService;

class CollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collections,
    ) {}

    /**
     * Collection page (SSR) with paginated products.
     */
    public function show(Request $request, string $slug): View
    {
        $collection = $this->collections->findBySlug($slug);

        abort_if($collection === null, 404);

        $products = $this->collections->products(
            $collection,
            page: max(1, (int) $request->input('page', 1)),
            perPage: 24,
            sort: $request->input('sort'),
        );

        return view('theme::pages.collection', [
            'collection' => $collection,
            'products' => $products,
        ]);
    }
}
