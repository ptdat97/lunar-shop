<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

/**
 * Sent when an order's status changes (e.g. dispatched, completed, cancelled).
 */
class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $previousStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order update — {$this->order->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'order::mail.order-status-updated', with: [
            'order' => $this->order,
            'previousStatus' => $this->previousStatus,
            'statusLabel' => config("lunar.orders.statuses.{$this->order->status}.label", $this->order->status),
        ]);
    }
}
