<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable JSON contract for a collection.
 *
 * @mixin \Lunar\Models\Collection
 */
class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->translateAttribute('name'),
            'slug' => $this->defaultUrl?->slug,
            'description' => $this->translateAttribute('description'),
        ];
    }
}
