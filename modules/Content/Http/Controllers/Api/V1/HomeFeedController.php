<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Content\Services\SectionRenderer;

class HomeFeedController extends Controller
{
    public function __construct(
        protected SectionRenderer $sections,
    ) {}

    /**
     * GET /api/v1/home-feed — the home page as an ordered list of sections, for
     * a headless client to render. Same sections, same order, same data as the
     * Blade home page (both go through SectionRenderer); this path emits JSON
     * instead of HTML.
     *
     * Each entry is `{type, settings, data}`: `settings` is what the admin
     * authored (headings, slides, image paths), `data` is what the section's
     * serializer resolved from Lunar (products, promotions) through the shared
     * API Resources. A client switches on `type` and renders its own component.
     *
     * A dynamic section with no serializer registered is omitted rather than
     * dumped raw — see SectionRenderer::sectionPayload().
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => $this->sections->payload('home'),
        ]);
    }
}
