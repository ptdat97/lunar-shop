<?php

namespace Modules\Inventory\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Catalog\Models\ProductSku;
use Modules\Core\Support\Queues;

/**
 * Notifies a "notify me" subscriber that a SKU they wanted is back in stock.
 */
class BackInStockMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Retry a few times with growing backoff for transient SMTP failures. */
    public int $tries = 3;

    /** @var list<int> seconds between retries (10s, 60s, 5m). */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public ProductSku $sku,
        public string $productName,
        public ?string $url = null,
    ) {
        $this->onQueue(Queues::NOTIFICATIONS);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Back in stock — {$this->productName}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'inventory::mail.back-in-stock', with: [
            'productName' => $this->productName,
            'url' => $this->url,
        ]);
    }
}
