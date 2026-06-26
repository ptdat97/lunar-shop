<?php

namespace Acme\Reviews;

use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Modules\Hook\Plugin\BasePlugin;
use Modules\Hook\Plugin\PluginConfig;
use Modules\Hook\Plugin\PluginSettings;
use Modules\Hook\Services\HookManager;
use Modules\Hook\Support\Hooks;

/**
 * Reference plugin (E3 dogfood): product reviews, built entirely on the public
 * Plugin SDK + hooks — ZERO edits to core. It proves the contract is enough:
 *
 *  - register(): bind its service.
 *  - boot(): load its routes, register its migrations, and enrich the product
 *    API payload via the `product.resource` FILTER (a `reviews` block appears on
 *    every product without ProductResource knowing this plugin exists).
 *  - install()/uninstall(): run/rollback its own migrations (artisan-driven).
 */
class ReviewsPlugin extends BasePlugin implements PluginConfig
{
    protected string $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__);
    }

    public function id(): string
    {
        return 'acme/reviews';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(Application $app): void
    {
        $app->singleton(ReviewService::class);
    }

    public function boot(HookManager $hooks): void
    {
        Route::middleware('api')->group($this->dir . '/routes/api.php');

        // Enrich every product payload with a rating summary — purely via the
        // shared filter, so SSR product-card, the product page, and the API all
        // get it with no change to ProductResource.
        $hooks->addFilter(Hooks::PRODUCT_RESOURCE, function (array $data, $product): array {
            $data['reviews'] = app(ReviewService::class)->summaryFor($product->id);

            return $data;
        });
    }

    public function install(): void
    {
        Artisan::call('migrate', [
            '--path' => $this->relativeMigrationsPath(),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    public function uninstall(): void
    {
        Artisan::call('migrate:rollback', [
            '--path' => $this->relativeMigrationsPath(),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    protected function relativeMigrationsPath(): string
    {
        return $this->dir . '/database/migrations';
    }

    // ─── PluginConfig: a tab on the admin Plugins page ───────────────────────

    public function configLabel(): string
    {
        return 'Reviews';
    }

    public function configSchema(): array
    {
        return [
            Toggle::make('auto_approve')
                ->label('Auto-approve new reviews')
                ->helperText('When off, reviews are hidden until an admin approves them.')
                ->default(true),
        ];
    }

    public function configState(): array
    {
        return [
            'auto_approve' => (bool) PluginSettings::for($this->id())->get('auto_approve', true),
        ];
    }

    public function saveConfig(array $data): void
    {
        PluginSettings::for($this->id())->put([
            'auto_approve' => (bool) ($data['auto_approve'] ?? true),
        ]);
    }
}
