<?php

namespace Modules\Core\Support;

/**
 * Named queues used across the app. Kept in one place so job/mail dispatch and
 * the Horizon supervisors in config/horizon.php stay in sync (no magic strings).
 *
 * Priority order (highest first) mirrors the supervisor layout: customer-facing
 * work (mail, notifications) is drained before heavy background media work so a
 * large image-regeneration batch never delays an order confirmation email.
 */
final class Queues
{
    /** Transactional order email (confirmation / paid / status update). */
    public const MAILS = 'mails';

    /** Customer notifications (back-in-stock, etc.). */
    public const NOTIFICATIONS = 'notifications';

    /** Heavy media work: on-demand + batch image conversions. */
    public const MEDIA = 'media';

    /** Everything else. */
    public const DEFAULT = 'default';

    /**
     * All queue names, in the drain priority order Horizon should honour.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::MAILS, self::NOTIFICATIONS, self::DEFAULT, self::MEDIA];
    }
}
