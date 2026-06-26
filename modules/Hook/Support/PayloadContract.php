<?php

namespace Modules\Hook\Support;

/**
 * The stable shape of each hookable payload — the contract plugins and clients
 * may rely on. Hooks let modules/plugins ADD keys; they must not REMOVE these
 * core ones. {@see \Tests\Feature\PayloadContractTest} pins them so a stray
 * filter or refactor that drops one fails CI (the same guarantee the API
 * Resources already give clients, now made explicit for the hook surface).
 *
 * This is the E4 "payload versioning" anchor: bump VERSION and update the lists
 * deliberately when the contract changes, so the change is reviewed, not silent.
 */
final class PayloadContract
{
    /** Contract version. Bump on a breaking change to any list below. */
    public const VERSION = '1.0';

    /**
     * Required top-level keys per hook payload. Keyed by the Hooks::* constant.
     *
     * @var array<string, list<string>>
     */
    public const REQUIRED_KEYS = [
        Hooks::PRODUCT_RESOURCE => ['id', 'name', 'slug', 'thumbnail', 'variants'],
        Hooks::CART_RESOURCE => ['id', 'lines', 'totals'],
        Hooks::ORDER_RESOURCE => ['id', 'reference', 'status', 'lines'],
    ];

    /**
     * The required keys for one payload, or [] if the payload isn't pinned.
     *
     * @return list<string>
     */
    public static function requiredKeys(string $hook): array
    {
        return self::REQUIRED_KEYS[$hook] ?? [];
    }
}
