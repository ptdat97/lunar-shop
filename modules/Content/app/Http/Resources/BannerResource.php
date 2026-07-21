<?php

namespace Modules\Content\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Models\Banner;

/**
 * Stable JSON contract for a storefront banner.
 *
 * Previously the raw model was serialised, exposing `active` and `sort` — server
 * bookkeeping the client has no use for, since the endpoint only ever returns
 * active banners already in sort order.
 *
 * @mixin Banner
 */
class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'position' => $this->position,
            'image' => $this->image,
            'mobile_image' => $this->mobile_image,
            'button' => $this->button_url ? [
                'text' => $this->button_text,
                'url' => $this->button_url,
            ] : null,
        ];
    }
}
