<?php

namespace Modules\Notification\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Lunar\Models\Order;
use Modules\Core\Support\Queues;
use Modules\Notification\Channels\PushChannel;
use Modules\Notification\Data\PushMessage;
use Modules\Order\Support\OrderStatus;

/**
 * In-app + push notice that an order moved on.
 *
 * Deliberately not a `mail` channel: the existing OrderStatusUpdatedMail already
 * sends that email, and it reaches guest buyers too (it resolves the address's
 * contact email, while a notification needs a User row). This adds the channels
 * an app can consume, and changes nothing about the mail that is already working.
 */
class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $previousStatus,
    ) {
        $this->onQueue(Queues::NOTIFICATIONS);
    }

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'order.status_changed',
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'status' => $this->order->status,
            'previous_status' => $this->previousStatus,
            'title' => $this->title(),
            'body' => $this->body(),
        ];
    }

    public function toPush(mixed $notifiable): PushMessage
    {
        return new PushMessage(
            title: $this->title(),
            body: $this->body(),
            // The app deep-links straight to the order.
            data: ['type' => 'order', 'order_id' => (string) $this->order->id],
        );
    }

    protected function title(): string
    {
        return __('notification.order_status.title', ['reference' => $this->order->reference]);
    }

    protected function body(): string
    {
        return __('notification.order_status.body', [
            'status' => OrderStatus::label($this->order->status),
        ]);
    }
}
