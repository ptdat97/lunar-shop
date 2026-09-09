<?php

namespace Modules\Core\Panel;

/**
 * One tab of admin-configurable feature settings.
 *
 * The old admin had eight of these as eight hand-written pages. They are all
 * the same shape — read a group of key/value settings, render a form, write it
 * back — so a group now declares its fields and where they live, and one
 * controller with one Vue page serves all of them.
 *
 * A group owns its own storage on purpose: most write to `app_settings` through
 * Settings, but the theme has its own table and the notification credentials go
 * through wrappers that know how to keep a secret. Forcing one store on all of
 * them would have meant moving data that has no reason to move.
 */
abstract class SettingsGroup
{
    /** Tab key and URL fragment, e.g. `payment`. */
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return array<int, Field> */
    abstract public function fields(): array;

    /**
     * Current values, nested to match the field names.
     *
     * @return array<string, mixed>
     */
    abstract public function values(): array;

    /**
     * Write the submitted values back. Secrets already arrive resolved: a blank
     * one has been replaced with what was stored.
     *
     * @param  array<string, mixed>  $data
     */
    abstract public function persist(array $data): void;

    public function description(): ?string
    {
        return null;
    }

    public function icon(): string
    {
        return 'sliders';
    }

    /**
     * Panel permission gating this tab. Feature settings change how the shop
     * charges and emails people, so they default to the core settings
     * permission rather than to anything looser.
     */
    public function permission(): string
    {
        return 'settings:core';
    }

    /** Ordering among the tabs. */
    public function priority(): int
    {
        return 50;
    }

    /** @return array<string, array<int, mixed>> */
    public function validationRules(): array
    {
        $rules = [];

        foreach ($this->fields() as $field) {
            $rules[$field->name] = $field->validationRules();

            foreach ($field->children() as $child) {
                $rules[$field->name.'.*.'.$child->name] = $child->validationRules();
            }
        }

        return $rules;
    }

    /**
     * The values as the form sees them: secrets blanked, so a credential is
     * never sent to a browser just because someone opened the settings screen.
     *
     * @return array<string, mixed>
     */
    public function formValues(): array
    {
        $values = $this->values();

        foreach ($this->fields() as $field) {
            if ($field->isSecret()) {
                data_set($values, $field->name, '');
            }
        }

        return $values;
    }

    /**
     * Whether a stored secret exists, per field name — the form shows "set,
     * leave blank to keep" instead of an empty box that looks unconfigured.
     *
     * @return array<string, bool>
     */
    public function secretsPresent(): array
    {
        $values = $this->values();
        $present = [];

        foreach ($this->fields() as $field) {
            if ($field->isSecret()) {
                $present[$field->name] = filled(data_get($values, $field->name));
            }
        }

        return $present;
    }

    /**
     * Put back whatever the form left blank for a secret.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolveSecrets(array $data): array
    {
        $stored = $this->values();

        foreach ($this->fields() as $field) {
            if (! $field->isSecret() || filled(data_get($data, $field->name))) {
                continue;
            }

            data_set($data, $field->name, data_get($stored, $field->name));
        }

        return $data;
    }
}
