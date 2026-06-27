<?php

namespace Acme\Workflow\Actions;

use Illuminate\Support\Facades\Mail;
use Modules\Platform\Workflow\WorkflowAction;

/**
 * Sends a plain notification email. config: ['to' => '…'|null, 'subject' => '…',
 * 'body' => '…']. When `to` is omitted, falls back to the context's
 * customer_email (e.g. order.paid). Strings may use {placeholders} from context.
 */
class EmailAction implements WorkflowAction
{
    public function id(): string
    {
        return 'notify.email';
    }

    public function label(): string
    {
        return 'Send email';
    }

    public function run(array $context, array $config): void
    {
        $to = $config['to'] ?? ($context['customer_email'] ?? null);

        if (! $to) {
            return;
        }

        $subject = $this->interpolate($config['subject'] ?? 'Notification', $context);
        $body = $this->interpolate($config['body'] ?? '', $context);

        Mail::raw($body, function ($message) use ($to, $subject): void {
            $message->to($to)->subject($subject);
        });
    }

    /** Replace {key} tokens with scalar context values. */
    protected function interpolate(string $text, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $text = str_replace('{' . $key . '}', (string) $value, $text);
            }
        }

        return $text;
    }
}
