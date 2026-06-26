<?php

namespace Modules\Hook\Plugin;

/**
 * Optional capability: a plugin that implements this contributes a config tab to
 * the admin Plugins page (Filament). Kept separate from {@see Plugin} so the
 * core contract stays minimal — a plugin without settings simply doesn't
 * implement it.
 *
 * The tab is a native Filament form: return its components from configSchema(),
 * current values from configState(), and persist in saveConfig(). State is
 * namespaced under the plugin id on the page, so two plugins' fields never
 * clash.
 */
interface PluginConfig
{
    /** Tab label shown in the Plugins page. */
    public function configLabel(): string;

    /**
     * Filament form components for this plugin's tab.
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public function configSchema(): array;

    /**
     * Current values to fill the form with (keyed by field name).
     *
     * @return array<string, mixed>
     */
    public function configState(): array;

    /**
     * Persist submitted values for this plugin.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveConfig(array $data): void;
}
