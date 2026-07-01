<?php

namespace Modules\Content\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Content\Services\ContentService;

class BannerController extends Controller
{
    public function __construct(
        protected ContentService $cms,
    ) {}

    /**
     * List active banners.
     */
    public function index(): JsonResponse
    {
        $banners = $this->cms->banners();

        return response()->json(['data' => $banners]);
    }
}