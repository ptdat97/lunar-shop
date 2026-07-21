<?php

namespace Modules\Notification\Drivers;

use Illuminate\Support\Facades\Log;
use Modules\Notification\Contracts\PushSender;
use Modules\Notification\Data\PushMessage;

/**
 * The default: records what *would* have been sent and delivers nothing.
 *
 * No push provider is wired yet, and inventing an FCM integration before there
 * is an app to receive it would be an abstraction with nothing behind it. The
 * contract exists so that adding one later is a config change, not a refactor.
 */
class NullPushSender implements PushSender
{
    public function send(array $tokens, PushMessage $message): array
    {
        Log::debug('push suppressed (no provider configured)', [
            'devices' => count($tokens),
            'title' => $message->title,
        ]);

        return []; // nothing to prune
    }
}
