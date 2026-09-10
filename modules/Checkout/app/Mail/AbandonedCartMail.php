<?php

namespace Modules\Checkout\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Lunar\Core\Models\Cart;
use Modules\Core\Support\Queues;

/**
 * "You left something behind" — one nudge per cart.
 *
 * Deliberately carries no discount. A recovery email that always comes with a
 * coupon teaches shoppers to abandon carts on purpose, and this shop's margins
 * are not the place to fund that lesson. The nudge is the reminder itself; the
 * coupon is a lever to pull later if the reminder alone does not convert.
 */
class AbandonedCartMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> seconds between retries (10s, 60s, 5m). */
    public array $backoff = [10, 60, 300];

    public function __construct(public Cart $cart) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.abandoned_cart.subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'checkout::mail.abandoned-cart', with: [
            'cart' => $this->cart->loadMissing(['lines.purchasable', 'currency']),
        ]);
    }

    /**
     * Queued onto the mail queue like every other transactional mail. Set here
     * rather than in the constructor because a Mailable built inside a test
     * with `Mail::fake()` never reaches a queue, and the constructor is the one
     * place both paths share.
     */
    public function queue(Factory $queue): mixed
    {
        $this->onQueue(Queues::MAILS);

        return parent::queue($queue);
    }
}
