<?php

namespace Modules\Notification\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;

/**
 * Stable JSON contract for one entry of the in-app inbox.
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = (array) $this->data;

        return [
            'id' => $this->id,
            // Our own semantic type ("order.status_changed"), not the PHP class:
            // clients must not couple to a namespace they cannot see.
            'type' => $data['type'] ?? 'unknown',
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'data' => Arr::except($data, ['type', 'title', 'body']),
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
