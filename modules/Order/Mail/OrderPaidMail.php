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
 * Sent when payment is confirmed (e.g. VNPay callback → payment-received).
 */
class OrderPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Payment received — {$this->order->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'order::mail.order-paid', with: [
            'order' => $this->order->loadMissing('lines'),
        ]);
    }
}
