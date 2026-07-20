<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Content\Http\Resources\BannerResource;
use Modules\Content\Services\ContentService;

class BannerController extends Controller
{
    public function __construct(
        protected ContentService $cms,
    ) {}

    /**
     * GET /api/v1/banners — active banners, already in sort order.
     *
     * Serialised through a Resource rather than dumping the model, which exposed
     * the `active` flag and `sort` column the client cannot act on.
     */
    public function index(): AnonymousResourceCollection
    {
        return BannerResource::collection($this->cms->banners());
    }
}
