<?php

namespace Modules\Checkout\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\DataTypes\ShippingOption;

/**
 * Stable JSON contract for a shipping option offered at checkout.
 *
 * @mixin ShippingOption
 */
class ShippingOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (string) $this->price->formatted(),
        ];
    }
}
