<?php

namespace Modules\CMS\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\CMS\Models\Page;
use Modules\CMS\Services\CMSService;

class PageController extends Controller
{
    public function __construct(
        protected CMSService $cms,
    ) {}

    /**
     * List published pages.
     */
    public function index(): JsonResponse
    {
        $pages = $this->cms->pages();

        return response()->json(['data' => $pages]);
    }

    /**
     * Show a single page by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $page = $this->cms->page($slug);

        if (! $page) {
            return response()->json(['message' => 'Page not found.'], 404);
        }

        return response()->json(['data' => $page]);
    }
}