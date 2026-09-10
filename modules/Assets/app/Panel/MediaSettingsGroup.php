<?php

namespace Modules\Assets\Panel;

use Modules\Assets\Services\MediaSettings;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\SettingsGroup;

/**
 * The sizes every image conversion is generated at.
 *
 * These drive `FashionMediaDefinitions`, so changing one changes the pixels of
 * every `small` / `medium` / `large` / `zoom` file the shop serves — plus the
 * two derived ones (`thumb` follows small, `webp` follows large).
 *
 * It exists because the conversion engine kept reading `MediaSettings::sizes()`
 * after the Filament era ended, while the only thing that ever *wrote* them —
 * the MediaImageSizes page — was deleted with the rest of the admin. The shop
 * was pinned to the defaults with no way back. `media-library:regenerate` does
 * not fill that gap: it regenerates at whatever sizes are configured, it cannot
 * change them.
 */
class MediaSettingsGroup extends SettingsGroup
{
    public function key(): string
    {
        return 'media';
    }

    public function label(): string
    {
        return __('admin.media.image_sizes');
    }

    public function description(): ?string
    {
        return __('admin.media.sizes_desc');
    }

    public function icon(): string
    {
        return 'image';
    }

    public function priority(): int
    {
        return 90;
    }

    public function permission(): string
    {
        return 'settings:core';
    }

    public function fields(): array
    {
        $fields = [];

        // Driven by the service's own key list, so a size added there shows up
        // here without anyone remembering to add it.
        foreach (MediaSettings::keys() as $key) {
            $default = MediaSettings::defaults()[$key];

            $fields[] = Field::number("{$key}.width", ucfirst($key).' — '.__('admin.media.width'))
                ->required()->rules('min:16', 'max:6000')->default($default['width'])->width(6);

            $fields[] = Field::number("{$key}.height", ucfirst($key).' — '.__('admin.media.height'))
                ->required()->rules('min:16', 'max:6000')->default($default['height'])->width(6);
        }

        return $fields;
    }

    public function values(): array
    {
        return app(MediaSettings::class)->sizes();
    }

    public function persist(array $data): void
    {
        // save() re-reads its own key list and clamps, so a missing or absurd
        // value falls back to the default rather than writing a broken size.
        app(MediaSettings::class)->save($data);
    }
}
