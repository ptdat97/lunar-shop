<?php

namespace Acme\Preorder;

use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Lunar\Models\Product;
use Modules\Platform\Plugin\BasePlugin;
use Modules\Platform\Plugin\PluginConfig;
use Modules\Platform\Plugin\PluginSettings;
use Modules\Platform\Services\HookManager;
use Modules\Platform\Support\Hooks;

/**
 * Pre-order plugin (second reference plugin): lets shoppers buy a flagged
 * product even when it's out of stock, and surfaces a pre-order badge. Built
 * entirely on the SDK + hooks, ZERO core edits.
 *
 * Enabling pre-order flips the product's variants to Lunar's native `always`
 * purchasable mode (see PreorderService), so buying out-of-stock works through
 * Lunar's own pipeline + the Inventory guard — no need to fight either. The
 * plugin then only adds presentation:
 *
 *  - product.resource: add a `preorder` block (enabled + expected date).
 */
class PreorderPlugin extends BasePlugin implements PluginConfig
{
    protected string $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__);
    }

    public function id(): string
    {
        return 'acme/preorder';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(Application $app): void
    {
        $app->singleton(PreorderService::class);
    }

    public function boot(HookManager $hooks): void
    {
        $hooks->addFilter(Hooks::PRODUCT_RESOURCE, function (array $data, Product $product): array {
            $badge = app(PreorderService::class)->badge($product->id);

            if ($badge) {
                $data['preorder'] = $badge;
            }

            return $data;
        });
    }

    public function install(): void
    {
        Artisan::call('migrate', [
            '--path' => $this->dir . '/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    public function uninstall(): void
    {
        Artisan::call('migrate:rollback', [
            '--path' => $this->dir . '/database/migrations',
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    // ─── PluginConfig: a tab on the admin Plugins page ───────────────────────

    public function configLabel(): string
    {
        return 'Pre-order';
    }

    public function configSchema(): array
    {
        return [
            TextInput::make('label')
                ->label('Badge label')
                ->helperText('Text shown on the pre-order badge in the storefront.')
                ->default('Pre-order')
                ->maxLength(40),
        ];
    }

    public function configState(): array
    {
        return [
            'label' => (string) PluginSettings::for($this->id())->get('label', 'Pre-order'),
        ];
    }

    public function saveConfig(array $data): void
    {
        PluginSettings::for($this->id())->put([
            'label' => trim((string) ($data['label'] ?? 'Pre-order')) ?: 'Pre-order',
        ]);
    }
}
