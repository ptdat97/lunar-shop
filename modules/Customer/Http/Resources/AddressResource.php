<?php

namespace Modules\Customer\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable JSON contract for a saved customer address.
 *
 * @mixin \Lunar\Models\Address
 */
class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'line_one' => $this->line_one,
            'line_two' => $this->line_two,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country_id' => $this->country_id,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'shipping_default' => (bool) $this->shipping_default,
            'billing_default' => (bool) $this->billing_default,
        ];
    }
}
