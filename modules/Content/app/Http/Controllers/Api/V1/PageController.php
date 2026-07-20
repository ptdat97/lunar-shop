<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Content\Http\Resources\PageResource;
use Modules\Content\Services\ContentService;
use Modules\Core\Support\ApiPagination;

class PageController extends Controller
{
    public function __construct(
        protected ContentService $cms,
    ) {}

    /**
     * GET /api/v1/pages — published pages.
     *
     * Serialised through a Resource: the raw Eloquent model used to go out on
     * the wire, so `published` and the timestamps were part of the contract by
     * accident and any new column would have leaked with it.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $pages = $this->cms->pages(
            perPage: ApiPagination::perPage($request),
            page: ApiPagination::page($request),
        );

        return PageResource::collection($pages->getCollection())
            ->additional(['meta' => ApiPagination::meta($pages)]);
    }

    /**
     * GET /api/v1/pages/{slug}
     */
    public function show(string $slug): PageResource|JsonResponse
    {
        $page = $this->cms->page($slug);

        if (! $page) {
            return response()->json(['message' => 'Page not found.'], 404);
        }

        return new PageResource($page);
    }
}
