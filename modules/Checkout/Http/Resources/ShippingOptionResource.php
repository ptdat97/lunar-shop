<?php

namespace Modules\Checkout\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable JSON contract for a shipping option offered at checkout.
 *
 * @mixin \Lunar\DataTypes\ShippingOption
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
