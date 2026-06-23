<?php

namespace Modules\Inventory\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\ProductVariant;

/**
 * Notifies a "notify me" subscriber that a variant they wanted is back in stock.
 */
class BackInStockMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProductVariant $variant,
        public string $productName,
        public ?string $url = null,
    ) {}

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
