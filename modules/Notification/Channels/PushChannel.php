<?php

namespace Modules\Notification\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Notification\Contracts\PushSender;
use Modules\Notification\Models\DeviceToken;
use Throwable;

/**
 * Laravel notification channel that fans a notification out to the recipient's
 * registered devices.
 *
 * Listed in a notification's `via()` as `PushChannel::class`; the notification
 * supplies the payload through `toPush()`.
 */
class PushChannel
{
    public function __construct(
        protected PushSender $sender,
    ) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $notifiable->getKey())
            ->pluck('token', 'id');

        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $dead = $this->sender->send($tokens->values()->all(), $notification->toPush($notifiable));
        } catch (Throwable $e) {
            // A push provider outage must never fail the business transaction
            // that raised the notification (an order was still placed).
            report($e);

            return;
        }

        $this->prune($tokens, $dead);
    }

    /**
     * Drop tokens the provider reported as invalid — an uninstalled app would
     * otherwise be pushed to forever.
     *
     * @param  Collection<int, string>  $tokens  id => token
     * @param  list<string>  $dead
     */
    protected function prune($tokens, array $dead): void
    {
        if ($dead === []) {
            return;
        }

        $ids = $tokens->filter(fn ($token) => in_array($token, $dead, true))->keys();

        DeviceToken::whereIn('id', $ids)->delete();

        Log::info('pruned dead device tokens', ['count' => $ids->count()]);
    }
}
