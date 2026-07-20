<?php

namespace Modules\Assets\Services;

use Modules\Core\Support\Settings;

/**
 * Admin-configurable Horizon worker scaling. Lets an admin tune each supervisor's
 * process count / memory / timeout / retries from the panel instead of editing
 * config/horizon.php. Values are stored in the `horizon` group of app_settings
 * (via {@see Settings}) and merged over the config/horizon.php defaults, then
 * pushed back into the live `horizon.*` config at boot by AssetsServiceProvider.
 *
 * Only the numeric scaling knobs are settable; structural keys (connection,
 * queue, balance, autoScalingStrategy…) stay in config/horizon.php so the queue
 * topology can't be broken from the UI. Every value is clamped to a safe range.
 */
class HorizonSettings
{
    /** Settings group key in app_settings. */
    protected const GROUP = 'horizon';

    /** Supervisors exposed to the admin. Must match config/horizon.php keys. */
    public const SUPERVISORS = ['supervisor-app', 'supervisor-media'];

    /** The tunable fields, with [min, max] clamp bounds. */
    public const FIELDS = [
        'maxProcesses' => [1, 50],
        'memory' => [32, 4096],   // MB
        'timeout' => [10, 1800],  // seconds
        'tries' => [1, 10],
    ];

    /**
     * Built-in defaults, read from the shipped config/horizon.php so behaviour is
     * unchanged until an admin overrides a value. Falls back to hard defaults if a
     * supervisor/field is somehow absent from config.
     *
     * @return array<string, array<string, int>>
     */
    public function defaults(): array
    {
        $fallback = [
            'maxProcesses' => 1,
            'memory' => 128,
            'timeout' => 60,
            'tries' => 3,
        ];

        $defaults = [];

        foreach (self::SUPERVISORS as $supervisor) {
            $config = config("horizon.defaults.{$supervisor}", []);

            foreach (array_keys(self::FIELDS) as $field) {
                $defaults[$supervisor][$field] = (int) ($config[$field] ?? $fallback[$field]);
            }
        }

        return $defaults;
    }

    /**
     * Current per-supervisor scaling (admin overrides merged over defaults),
     * every value clamped to its safe range.
     *
     * @return array<string, array<string, int>>
     */
    public function supervisors(): array
    {
        $stored = app(Settings::class)->group(self::GROUP, []);
        $defaults = $this->defaults();

        $result = [];

        foreach (self::SUPERVISORS as $supervisor) {
            foreach (self::FIELDS as $field => [$min, $max]) {
                $value = $stored[$supervisor][$field] ?? $defaults[$supervisor][$field];
                $result[$supervisor][$field] = $this->clamp((int) $value, $min, $max);
            }
        }

        return $result;
    }

    /**
     * Persist new scaling values (clamped, only known supervisors/fields kept).
     *
     * @param  array<string, mixed>  $data  form state keyed by supervisor
     */
    public function save(array $data): void
    {
        $clean = [];

        foreach (self::SUPERVISORS as $supervisor) {
            foreach (self::FIELDS as $field => [$min, $max]) {
                $value = $data[$supervisor][$field] ?? $this->defaults()[$supervisor][$field];
                $clean[$supervisor][$field] = $this->clamp((int) $value, $min, $max);
            }
        }

        app(Settings::class)->put(self::GROUP, $clean);
    }

    protected function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
