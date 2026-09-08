<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\Core\Models\Collection;

/**
 * Stable JSON contract for a collection.
 *
 * @mixin Collection
 */
class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->translate('name'),
            'slug' => $this->defaultUrl?->slug,
            'description' => $this->translate('description'),
        ];
    }
}
