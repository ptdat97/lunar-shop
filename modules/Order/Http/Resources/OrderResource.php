<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Lunar\Models\Order;
use Modules\Order\Support\OrderStatus;

/**
 * Stable JSON contract for a customer-facing order (list + detail). Shared by
 * the storefront account pages and /api/v1/orders.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            // The raw handle stays (clients switch on it); the label is what a
            // human reads, localised — Lunar's config only has English, and only
            // for the four statuses it ships with.
            'status_label' => OrderStatus::label($this->status),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'total' => $this->total?->formatted(),
            'sub_total' => $this->subTotal?->formatted(),
            'shipping_total' => $this->shippingTotal?->formatted(),
            'tax_total' => $this->taxTotal?->formatted(),
            // Returnable when the order is in a paid/fulfilled state (customer can
            // open an RMA from the account order-detail). ReturnService enforces
            // the same rule — this flag only hides the button.
            'can_return' => OrderStatus::isReturnable($this->status),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'description' => $line->description,
                'identifier' => $line->identifier,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice?->formatted(),
                'sub_total' => $line->subTotal?->formatted(),
            ])->values()),
            'shipping_address' => $this->whenLoaded('shippingAddress', fn () => $this->address($this->shippingAddress)),
            'billing_address' => $this->whenLoaded('billingAddress', fn () => $this->address($this->billingAddress)),
        ];

        return $data;
    }

    /**
     * Flatten an order address into a presentational shape.
     */
    protected function address($address): ?array
    {
        if (! $address) {
            return null;
        }

        return [
            'name' => trim("{$address->first_name} {$address->last_name}"),
            'line_one' => $address->line_one,
            'line_two' => $address->line_two,
            'city' => $address->city,
            'state' => $address->state,
            'postcode' => $address->postcode,
            'contact_phone' => $address->contact_phone,
        ];
    }
}
