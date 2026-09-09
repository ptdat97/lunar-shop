<?php

namespace Modules\Core\Panel;

use Modules\Core\Support\Settings;

/**
 * A settings group kept in `app_settings` under one top-level key — which is
 * most of them.
 *
 * `Settings::put()` replaces the whole group, so a subclass must write every
 * key it owns on every save. Declaring the fields is what guarantees that: the
 * payload is rebuilt from the schema, not merged into whatever was there.
 */
abstract class StoredSettingsGroup extends SettingsGroup
{
    /**
     * The `app_settings` key this group lives under. Usually the same as the
     * tab key, but not always — `catalog` spans several stored groups.
     */
    protected function storageKey(): string
    {
        return $this->key();
    }

    /** Config path the stored group falls back to when a key is unset. */
    protected function fallbackConfig(): string
    {
        return $this->storageKey();
    }

    public function values(): array
    {
        return app(Settings::class)->group(
            $this->storageKey(),
            (array) config($this->fallbackConfig(), []),
        );
    }

    public function persist(array $data): void
    {
        app(Settings::class)->put($this->storageKey(), $this->payload($data));
    }

    /**
     * The exact array to store. Defaults to the declared fields, which is right
     * whenever the form's shape and the stored shape are the same; a group
     * whose storage differs (a renamed key, a derived value) overrides this.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function payload(array $data): array
    {
        $payload = [];

        foreach ($this->fields() as $field) {
            data_set($payload, $field->name, data_get($data, $field->name));
        }

        return $payload;
    }
}
