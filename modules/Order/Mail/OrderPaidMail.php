<?php

namespace Modules\Order\Mail;

use App\Support\Queues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;
use Modules\Order\Services\InvoiceService;

/**
 * Sent when payment is confirmed (e.g. VNPay callback → payment-received).
 * The paid invoice PDF is attached (generated in the mail's locale).
 */
class OrderPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Retry a few times with growing backoff for transient SMTP failures. */
    public int $tries = 3;

    /** @var list<int> seconds between retries (10s, 60s, 5m). */
    public array $backoff = [10, 60, 300];

    public function __construct(public Order $order)
    {
        $this->onQueue(Queues::MAILS);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.paid.subject', ['reference' => $this->order->reference]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'order::mail.order-paid', with: [
            'order' => $this->order->loadMissing('lines'),
        ]);
    }

    /**
     * Attach the invoice PDF. Built lazily at render time so it inherits the
     * mail's locale (set by OrderMailer) — the invoice is bilingual (EN/VI).
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $invoices = app(InvoiceService::class);

        return [
            Attachment::fromData(fn () => $invoices->bytes($this->order), $invoices->filename($this->order))
                ->withMime('application/pdf'),
        ];
    }
}
