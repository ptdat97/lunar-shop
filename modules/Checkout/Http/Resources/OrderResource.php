<?php

namespace Modules\Checkout\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable JSON contract for a placed order.
 *
 * @mixin \Lunar\Models\Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'total' => $this->total?->formatted(),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'sub_total' => $line->sub_total?->formatted(),
            ])->values()),
        ];
    }
}
