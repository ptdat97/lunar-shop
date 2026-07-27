<?php

namespace Modules\Content\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Models\Page;

/**
 * Stable JSON contract for a CMS page.
 *
 * The endpoint used to return the Eloquent model straight out of the service, so
 * every column — including the `published` flag and timestamps — was part of the
 * contract by accident, and any new column would have leaked automatically.
 *
 * `featured_image` is stored as a Media Library Asset id (picked via
 * MediaPicker) — resolved to a browser URL here, the single place that knows
 * how the app stores images (modules/Assets' media_url()).
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'featured_image' => media_url($this->featured_image),
            'content' => $this->content,
            'seo' => [
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'og' => $this->og_data,
            ],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
