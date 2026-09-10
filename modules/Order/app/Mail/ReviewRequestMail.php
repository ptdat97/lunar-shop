<?php

namespace Modules\Order\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Lunar\Core\Models\Order;
use Modules\Core\Support\Queues;

/**
 * "How did it fit?" — one request per order, sent after the goods have landed.
 *
 * Links straight to each product's review form rather than to a generic
 * "leave a review" page: the friction between wanting to review and finding
 * where to do it is where most review requests die.
 */
class ReviewRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> seconds between retries (10s, 60s, 5m). */
    public array $backoff = [10, 60, 300];

    public function __construct(public Order $order)
    {
        $this->onQueue(Queues::MAILS);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.review_request.subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'order::mail.review-request', with: [
            'order' => $this->order->loadMissing('lines'),
            'products' => $this->reviewableProducts(),
        ]);
    }

    /**
     * The distinct products in the order, with the slug the review form lives on.
     *
     * Distinct because a shopper who bought the same shirt in two sizes should
     * be asked once, not twice — the review is about the product, not the line.
     *
     * @return array<int, array{name: string, slug: string|null}>
     */
    protected function reviewableProducts(): array
    {
        return $this->order->lines
            ->map(fn ($line) => $line->purchasable?->product)
            ->filter()
            ->unique('id')
            ->map(fn ($product) => [
                'name' => (string) $product->translate('name'),
                'slug' => $product->defaultUrl?->slug,
            ])
            // A product with no URL has nowhere to send the shopper.
            ->filter(fn (array $row) => filled($row['slug']))
            ->values()
            ->all();
    }
}
