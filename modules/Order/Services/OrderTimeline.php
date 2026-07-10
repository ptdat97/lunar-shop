<?php

namespace Modules\Order\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Modules\Order\Support\OrderStatus;

/**
 * An order's status history, read from Lunar's activity log.
 *
 * No new table: `Lunar\Models\Order` already `use LogsActivity`, and Lunar's own
 * observer writes a dedicated `status-update` entry carrying `{previous, new}`.
 * Building an `order_timeline` table would duplicate what the commerce core
 * already records (Principle #1).
 *
 * Only `status-update` rows are exposed. The same log also holds generic
 * `updated` rows whose properties are a full column diff — internal state the
 * customer must never see.
 */
class OrderTimeline
{
    /**
     * @return Collection<int, array{status:string, status_label:string, previous_status:?string, at:string}>
     */
    public function for(Order $order): Collection
    {
        $rows = DB::table('activity_log')
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->where('event', 'status-update')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['properties', 'created_at']);

        $entries = $rows->map(function ($row) {
            $properties = json_decode((string) $row->properties, true) ?: [];
            $status = $properties['new'] ?? null;

            if (! $status) {
                return null;
            }

            return [
                'status' => $status,
                'status_label' => OrderStatus::label($status),
                'previous_status' => $properties['previous'] ?? null,
                'at' => (string) $row->created_at,
            ];
        })->filter()->values();

        return $entries->isEmpty() ? $this->seedFromOrder($order) : $entries;
    }

    /**
     * Orders placed before the activity log had anything to say still deserve a
     * timeline: fall back to the one transition we can prove happened.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function seedFromOrder(Order $order): Collection
    {
        return collect([[
            'status' => $order->status,
            'status_label' => OrderStatus::label($order->status),
            'previous_status' => null,
            'at' => (string) ($order->placed_at ?? $order->created_at),
        ]]);
    }
}
