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
 * Sent when an order is placed (COD/bank, and VNPay on authorize).
 */
class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Order confirmed — {$this->order->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'order::mail.order-confirmation', with: [
            'order' => $this->order->loadMissing('lines'),
        ]);
    }
}
