<?php

namespace Modules\Order\Mail;

use Modules\Core\Support\Queues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\ReturnRequest;

/**
 * Sent when a return request changes state (received / approved / rejected /
 * refunded). Localised via the mail.* strings; queued like order mail.
 */
class ReturnStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public ReturnRequest $return)
    {
        $this->onQueue(Queues::MAILS);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.return.subject', [
            'reference' => $this->return->reference,
            'status' => $this->statusLabel(),
        ]));
    }

    public function content(): Content
    {
        return new Content(markdown: 'order::mail.return-status', with: [
            'return' => $this->return->loadMissing('order'),
            'statusLabel' => $this->statusLabel(),
        ]);
    }

    protected function statusLabel(): string
    {
        return __('mail.return.status_' . $this->return->status);
    }
}
