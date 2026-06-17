<?php

namespace Modules\CMS\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\CMS\Services\CMSService;

class BannerController extends Controller
{
    public function __construct(
        protected CMSService $cms,
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