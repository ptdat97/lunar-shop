<?php

namespace Modules\SectionBuilder\Filament\Resources\PageSectionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Modules\SectionBuilder\Filament\Resources\PageSectionResource;
use Modules\SectionBuilder\Models\PageSection;
use Modules\SectionBuilder\Support\SectionSchemas;

/**
 * Single-screen management for page sections: table + slide-over modal for
 * create/edit. No separate create/edit routes.
 */
class ManagePageSections extends ManageRecords
{
    protected static string $resource = PageSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->mutateFormDataUsing(function (array $data): array {
                    // Append new sections to the end of their page.
                    $data['sort'] ??= (int) PageSection::where('page_handle', $data['page_handle'] ?? 'home')->max('sort') + 1;

                    // If the admin didn't fill content, seed the template defaults
                    // for the chosen type so the section renders correctly.
                    $data['settings'] = static::seedDefaults($data['type'] ?? '', $data['settings'] ?? []);

                    return $data;
                }),
        ];
    }

    /**
     * Merge template defaults into the submitted settings: if a content list
     * (slides/items) is missing or only contains blank rows, use the defaults.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    protected static function seedDefaults(string $type, array $settings): array
    {
        $defaults = SectionSchemas::defaults($type);

        foreach (['slides', 'items'] as $listKey) {
            if (! isset($defaults[$listKey])) {
                continue;
            }

            $rows = array_filter(
                $settings[$listKey] ?? [],
                fn ($row) => is_array($row) && collect($row)->filter(fn ($v) => filled($v) && $v !== [])->isNotEmpty(),
            );

            if (empty($rows)) {
                $settings[$listKey] = $defaults[$listKey];
            }
        }

        // Scalar defaults (heading/limit/etc.) only when not provided.
        foreach ($defaults as $key => $value) {
            if (! is_array($value) && blank($settings[$key] ?? null)) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }
}
