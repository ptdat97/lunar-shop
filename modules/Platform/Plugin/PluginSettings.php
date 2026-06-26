<?php

namespace Modules\Platform\Plugin;

use Modules\Platform\Models\PluginState;

/**
 * Tiny per-plugin key-value store, backed by the `plugins.settings` JSON bag.
 * The SDK owns the storage so a plugin needn't ship its own settings table for
 * a handful of options; a plugin reads/writes only its own slice (keyed by id).
 *
 *   PluginSettings::for('acme/reviews')->get('auto_approve', true);
 *   PluginSettings::for('acme/reviews')->put(['auto_approve' => false]);
 */
class PluginSettings
{
    public function __construct(
        protected string $pluginId,
    ) {}

    public static function for(string $pluginId): self
    {
        return new self($pluginId);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return (array) (PluginState::where('plugin_id', $this->pluginId)->value('settings') ?? []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    /**
     * Merge values into the plugin's settings bag (creating the install record
     * if needed, so config can be set even before install).
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        $state = PluginState::firstOrNew(['plugin_id' => $this->pluginId]);
        $state->settings = array_merge($this->all(), $values);
        $state->save();
    }
}
