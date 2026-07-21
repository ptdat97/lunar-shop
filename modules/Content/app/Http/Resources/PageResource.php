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
            'featured_image' => $this->featured_image,
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
