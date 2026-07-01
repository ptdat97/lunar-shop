<?php

namespace Modules\Order\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\Models\Order;
use Modules\Order\Mail\ReturnStatusMail;
use Modules\Order\Models\ReturnRequest;

/**
 * Return / RMA workflow: a customer opens a request against a paid order (line +
 * quantity + reason); staff approve (optionally refunding via the payment
 * gateway) or reject. All state changes fire the return-status email.
 */
class ReturnService
{
    /**
     * Open a return request for an order.
     *
     * @param  array<int, array{order_line_id:int, quantity:int}>  $lines
     */
    public function open(Order $order, array $lines, string $reason, ?string $comment = null): ReturnRequest
    {
        $order->loadMissing('lines');
        $valid = $this->validLines($order, $lines);

        if (empty($valid)) {
            throw new \InvalidArgumentException('No returnable lines selected.');
        }

        return DB::transaction(function () use ($order, $valid, $reason, $comment) {
            $request = ReturnRequest::create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'reference' => $this->reference(),
                'status' => ReturnRequest::REQUESTED,
                'reason' => $reason,
                'comment' => $comment,
            ]);

            foreach ($valid as $line) {
                $request->lines()->create([
                    'order_line_id' => $line['order_line_id'],
                    'quantity' => $line['quantity'],
                ]);
            }

            $request->load('lines');
            $this->notify($request);

            return $request;
        });
    }

    /**
     * Approve a request; optionally refund the requested value via the gateway.
     */
    public function approve(ReturnRequest $request, bool $refund = false, ?string $staffNote = null): ReturnRequest
    {
        $request->update([
            'status' => ReturnRequest::APPROVED,
            'staff_note' => $staffNote,
            'resolved_at' => now(),
        ]);

        if ($refund) {
            return $this->refund($request); // refund() sends its own notification
        }

        $this->notify($request);

        return $request->fresh('lines');
    }

    public function reject(ReturnRequest $request, ?string $staffNote = null): ReturnRequest
    {
        $request->update([
            'status' => ReturnRequest::REJECTED,
            'staff_note' => $staffNote,
            'resolved_at' => now(),
        ]);

        $this->notify($request);

        return $request->fresh();
    }

    /**
     * Refund the value of the request's lines via the payment gateway (VNPay /
     * MoMo). Falls back to marking refunded without a gateway call for offline
     * (cod/bank) orders, which are settled manually.
     */
    public function refund(ReturnRequest $request): ReturnRequest
    {
        $amount = $this->refundableAmount($request);
        $order = $request->order;

        $refundService = app(\Modules\Checkout\Services\RefundService::class);

        // Only call the gateway when there's a gateway capture to refund.
        if ($refundService->captureTransaction($order)) {
            $result = $refundService->refund($order, $amount, 'return:' . $request->reference);

            if (! $result->success) {
                throw new \RuntimeException($result->message ?: 'Gateway refund failed.');
            }
        }

        $request->update([
            'status' => ReturnRequest::REFUNDED,
            'refund_amount' => $amount,
            'resolved_at' => now(),
        ]);

        $request = $request->fresh();
        $this->notify($request);

        return $request;
    }

    /**
     * Email the customer about the current return status (locale-aware, via
     * OrderMailer's recipient/locale rules). No-op when no recipient resolves.
     */
    protected function notify(ReturnRequest $request): void
    {
        $order = $request->order;
        if (! $order) {
            return;
        }

        app(OrderMailer::class)->send($order, new ReturnStatusMail($request));
    }

    /**
     * Total value (minor units) of the request's returned lines, prorated by
     * quantity of each order line.
     */
    public function refundableAmount(ReturnRequest $request): int
    {
        $request->loadMissing('lines.orderLine');

        return (int) $request->lines->sum(function ($line) {
            $orderLine = $line->orderLine;
            if (! $orderLine || ! $orderLine->quantity) {
                return 0;
            }
            // Per-unit value from the line total, times the returned quantity.
            $perUnit = (int) round((int) $orderLine->total->value / (int) $orderLine->quantity);

            return $perUnit * (int) $line->quantity;
        });
    }

    /**
     * Keep only lines that belong to the order and whose quantity is 1..line qty.
     *
     * @param  array<int, array{order_line_id:int, quantity:int}>  $lines
     * @return array<int, array{order_line_id:int, quantity:int}>
     */
    protected function validLines(Order $order, array $lines): array
    {
        $byId = $order->lines->keyBy('id');

        return collect($lines)
            ->map(fn ($l) => [
                'order_line_id' => (int) ($l['order_line_id'] ?? 0),
                'quantity' => (int) ($l['quantity'] ?? 0),
            ])
            ->filter(function ($l) use ($byId) {
                $orderLine = $byId->get($l['order_line_id']);

                return $orderLine && $l['quantity'] >= 1 && $l['quantity'] <= $orderLine->quantity;
            })
            ->values()
            ->all();
    }

    protected function reference(): string
    {
        do {
            $ref = 'RMA-' . strtoupper(Str::random(8));
        } while (ReturnRequest::where('reference', $ref)->exists());

        return $ref;
    }
}
