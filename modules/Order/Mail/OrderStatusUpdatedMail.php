<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;
use App\Support\Queues;

/**
 * Sent when an order's status changes (e.g. dispatched, completed, cancelled).
 */
class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Retry a few times with growing backoff for transient SMTP failures. */
    public int $tries = 3;

    /** @var list<int> seconds between retries (10s, 60s, 5m). */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public Order $order,
        public string $previousStatus,
    ) {
        $this->onQueue(Queues::MAILS);
    }

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
