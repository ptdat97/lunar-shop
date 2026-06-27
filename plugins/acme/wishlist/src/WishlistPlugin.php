<?php

namespace Acme\Wishlist;

use Illuminate\Support\Facades\Route;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Services\HookManager;

/**
 * Wishlist as a first-party plugin (Phase 4: extracted from the Customer module
 * with ZERO behaviour change). Same routes/names/middleware and the same
 * wishlist_items table — only the code now lives behind the Plugin SDK, decoupled
 * from Customer. Enabled by default in config/plugins.php since it's core
 * storefront functionality (header heart, account, wishlist page).
 *
 * The wishlist_items table was already provisioned by a Customer migration, so
 * there's nothing to migrate on install — the plugin reuses the existing table.
 */
class WishlistPlugin extends BasePlugin
{
    protected string $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__);
    }

    public function id(): string
    {
        return 'acme/wishlist';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function boot(HookManager $hooks): void
    {
        // The route files declare their own middleware groups (storefront / web /
        // auth:sanctum) — load them as-is so paths/names/middleware are identical
        // to the pre-split Customer routes.
        Route::group([], $this->dir . '/routes/web.php');
        Route::group([], $this->dir . '/routes/api.php');
    }
}
