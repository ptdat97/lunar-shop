<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\Review;

/**
 * Stable JSON contract for a product review.
 *
 * The shape used to be built inline in the controller (coding standards §6 asks
 * every endpoint to serialise through a JsonResource), so nothing stopped the
 * API and any future consumer from drifting apart.
 *
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'rating' => $this->rating,
            'body' => $this->body,
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
