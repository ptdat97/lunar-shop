<?php

namespace Modules\Order\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lunar\Models\Order;
use Modules\Checkout\Services\RefundService;
use Modules\Order\Mail\ReturnStatusMail;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Models\ReturnRequestLine;
use Modules\Order\Support\OrderStatus;

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

        // There is nothing to send back from an order that was never paid for
        // and never shipped. `can_return` in OrderResource only hid the button;
        // the endpoint itself accepted an RMA against an `awaiting-payment`
        // order, and staff could then refund it.
        if (! OrderStatus::isReturnable($order->status)) {
            throw new InvalidArgumentException('This order cannot be returned.');
        }

        $request = DB::transaction(function () use ($order, $lines, $reason, $comment) {
            // Validate INSIDE the transaction, after locking the order's existing
            // return lines: two requests opened at once would otherwise each see
            // the same remaining quantity and both pass.
            $this->lockReturnLines($order);

            $valid = $this->validLines($order, $lines);

            if (empty($valid)) {
                throw new InvalidArgumentException('No returnable lines selected.');
            }

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

            return $request->load('lines');
        });

        // Email is a side effect that cannot be rolled back — it must not run
        // inside the transaction (coding standards §4).
        $this->notify($request);

        return $request;
    }

    /**
     * Take a row lock over the order's existing return lines, so a concurrent
     * request cannot claim quantity we are about to spend.
     */
    protected function lockReturnLines(Order $order): void
    {
        ReturnRequestLine::query()
            ->join('return_requests as rr', 'rr.id', '=', 'return_request_lines.return_request_id')
            ->where('rr.order_id', $order->id)
            ->lockForUpdate()
            ->get(['return_request_lines.id']);
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
        $order = $request->order;

        // Claim the request under a lock before anything with a side effect runs.
        // Two admins clicking "Refund" together, or one double-click, must not both
        // reach the gateway. On a gateway order `RefundService` would still cap the
        // total, but a COD/bank order never touches RefundService — nothing else
        // stops the second call from paying out and emailing the customer again.
        $amount = DB::transaction(function () use ($request, $order) {
            $fresh = ReturnRequest::whereKey($request->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status === ReturnRequest::REFUNDED) {
                throw new \RuntimeException('This return has already been refunded.');
            }

            $amount = $this->cappedRefund($fresh, $order);

            if ($amount <= 0) {
                throw new \RuntimeException('Nothing left to refund on this order.');
            }

            $fresh->update([
                'status' => ReturnRequest::REFUNDED,
                'refund_amount' => $amount,
                'resolved_at' => now(),
            ]);

            return $amount;
        });

        $refundService = app(RefundService::class);

        // Gateway call stays *outside* the transaction (§4: no un-rollbackable
        // side effects inside). Only call it when there's a capture to refund;
        // COD/bank orders are settled by hand.
        if ($refundService->captureTransaction($order)) {
            $result = $refundService->refund($order, $amount, 'return:'.$request->reference);

            if (! $result->success) {
                // Release the claim so staff can retry once the gateway recovers.
                ReturnRequest::whereKey($request->id)->update([
                    'status' => ReturnRequest::APPROVED,
                    'refund_amount' => null,
                ]);

                throw new \RuntimeException($result->message ?: 'Gateway refund failed.');
            }
        }

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
     * The request's value, never more than the order still has left to give.
     *
     * Defence in depth. `validLines()` already stops a line being claimed twice,
     * but an offline (cod/bank) order has no gateway to enforce a ceiling — the
     * gateway path is capped by RefundService — so the total refunded across
     * every request on this order must be bounded here too.
     *
     * Note it excludes `$request` itself (`whereKeyNot`), so it cannot stop the
     * *same* request being refunded twice. {@see self::refund()} owns that, by
     * claiming the request under a lock before any side effect runs.
     */
    protected function cappedRefund(ReturnRequest $request, Order $order): int
    {
        $orderTotal = (int) ($order->total?->value ?? 0);

        $alreadyRefunded = (int) ReturnRequest::query()
            ->where('order_id', $order->id)
            ->where('status', ReturnRequest::REFUNDED)
            ->whereKeyNot($request->id)
            ->sum('refund_amount');

        $remaining = max(0, $orderTotal - $alreadyRefunded);

        return min($this->refundableAmount($request), $remaining);
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
     * How many units of each order line are still returnable, after subtracting
     * everything already claimed by this order's other return requests.
     *
     * Rejected requests release their claim; every other status (requested,
     * approved, refunded, completed) holds it.
     *
     * @return array<int, int> order_line_id => remaining quantity
     */
    public function remainingQuantities(Order $order, ?int $ignoreRequestId = null): array
    {
        $order->loadMissing('lines');

        $claimed = ReturnRequestLine::query()
            ->join('return_requests as rr', 'rr.id', '=', 'return_request_lines.return_request_id')
            ->where('rr.order_id', $order->id)
            ->where('rr.status', '!=', ReturnRequest::REJECTED)
            ->when($ignoreRequestId, fn ($q) => $q->where('rr.id', '!=', $ignoreRequestId))
            ->groupBy('return_request_lines.order_line_id')
            ->selectRaw('return_request_lines.order_line_id as line_id, SUM(return_request_lines.quantity) as claimed')
            ->pluck('claimed', 'line_id');

        return $order->lines
            ->mapWithKeys(fn ($line) => [
                $line->id => max(0, (int) $line->quantity - (int) ($claimed[$line->id] ?? 0)),
            ])
            ->all();
    }

    /**
     * Keep only lines that belong to the order and whose quantity is within what
     * is *still* returnable.
     *
     * Previously this compared against the order line's full quantity, so the
     * same line could be claimed by request after request — two RMAs each
     * refunding the whole line drained more than the order was ever worth (the
     * gateway path was capped by RefundService, but COD/bank had no cap at all).
     *
     * @param  array<int, array{order_line_id:int, quantity:int}>  $lines
     * @return array<int, array{order_line_id:int, quantity:int}>
     */
    protected function validLines(Order $order, array $lines): array
    {
        $remaining = $this->remainingQuantities($order);

        return collect($lines)
            ->map(fn ($l) => [
                'order_line_id' => (int) ($l['order_line_id'] ?? 0),
                'quantity' => (int) ($l['quantity'] ?? 0),
            ])
            ->filter(function ($l) use ($remaining) {
                $available = $remaining[$l['order_line_id']] ?? 0;

                return $l['quantity'] >= 1 && $l['quantity'] <= $available;
            })
            ->values()
            ->all();
    }

    protected function reference(): string
    {
        do {
            $ref = 'RMA-'.strtoupper(Str::random(8));
        } while (ReturnRequest::where('reference', $ref)->exists());

        return $ref;
    }
}
