<?php

namespace Modules\Assets\Filament\Forms;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;
use Lunar\Models\Asset;

/**
 * Reusable media picker backed by the Media Library (Lunar Asset + Spatie
 * MediaLibrary). It stores the selected Lunar Asset id so the storefront can
 * resolve the original plus any conversion (thumb/webp/large/zoom…) and so a
 * file replaced in the library updates everywhere automatically.
 *
 * The underlying column stays a string (e.g. banner.image, lookbook.cover_image,
 * page.featured_image) — it now holds the asset id instead of a path.
 *
 * Usage:
 *   MediaPicker::make('image')->label('Banner image')
 *   MediaPicker::make('cover_image', type: 'image')
 *   MediaPicker::make('payment', type: 'image', multiple: true)   // array of asset ids
 *
 * @param  string|null  $type  Restrict to a single library type (image|video|document).
 * @param  bool  $multiple  Store an array of asset ids instead of a single id — for
 *                          fields that need several library files (e.g. payment badges).
 */
class MediaPicker
{
    /**
     * Build the picker field.
     */
    public static function make(string $name, ?string $type = null, bool $multiple = false): Select
    {
        $field = Select::make($name)
            ->searchable()
            ->preload()
            ->native(false)
            ->live()
            ->placeholder(__('admin.media.pick'))
            ->options(fn () => static::optionsQuery($type)->limit(50)->get()
                ->mapWithKeys(fn (Asset $a) => [$a->id => static::optionLabel($a)])
                ->all())
            // Search by media name (already-saved value may be outside the cap).
            ->getSearchResultsUsing(fn (string $search) => static::optionsQuery($type)
                ->whereHas('file', fn ($m) => $m->where('name', 'like', "%{$search}%"))
                ->limit(50)
                ->get()
                ->mapWithKeys(fn (Asset $a) => [$a->id => static::optionLabel($a)])
                ->all())
            ->suffixAction(static::openLibraryAction());

        if ($multiple) {
            return $field
                ->multiple()
                ->getOptionLabelsUsing(fn (array $values): array => collect($values)
                    ->mapWithKeys(fn ($id) => [$id => static::labelForId($id)])
                    ->filter()
                    ->all())
                // Reorderable thumbnail strip instead of the single-image helper text.
                ->helperText(fn ($state) => static::previewStrip($state))
                ->dehydrateStateUsing(fn ($state) => array_values(array_filter((array) $state)));
        }

        return $field
            ->getOptionLabelUsing(fn ($value): ?string => static::labelForId($value))
            // Live thumbnail preview shown beneath the select.
            ->helperText(fn ($state) => static::previewHint($state))
            ->dehydrateStateUsing(fn ($state) => $state ?: null);
    }

    /**
     * Base query: assets that have a media file, newest first, optionally
     * restricted to a single logical type.
     */
    protected static function optionsQuery(?string $type = null)
    {
        return Asset::query()
            ->whereHas('file')
            ->with('file')
            ->when($type, fn ($q) => $q->whereHas(
                'file',
                fn ($m) => $m->where('custom_properties->type', $type),
            ))
            ->latest('id');
    }

    protected static function optionLabel(Asset $asset): string
    {
        $media = $asset->file;

        if (! $media) {
            return 'Asset #'.$asset->id;
        }

        $type = $media->getCustomProperty('type') ?? 'file';

        return $media->name.' ('.ucfirst($type).', '.$media->humanReadableSize.')';
    }

    protected static function labelForId($id): ?string
    {
        if (! $id) {
            return null;
        }

        $asset = Asset::with('file')->find($id);

        return $asset ? static::optionLabel($asset) : null;
    }

    /**
     * HTML hint with a small thumbnail of the selected file.
     */
    protected static function previewHint($id): ?HtmlString
    {
        if (! $id) {
            return null;
        }

        return static::thumbHtml($id);
    }

    /**
     * HTML strip of small thumbnails for a multiple-picker's selected ids, in
     * the order they were selected/reordered.
     *
     * @param  array<int, mixed>|null  $ids
     */
    protected static function previewStrip(?array $ids): ?HtmlString
    {
        $ids = array_filter((array) $ids);

        if (empty($ids)) {
            return null;
        }

        $html = collect($ids)->map(fn ($id) => (string) static::thumbHtml($id))->implode('');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;gap:8px;">'.$html.'</div>'
        );
    }

    /**
     * HTML for one selected file's thumbnail/preview (image/video/document).
     */
    protected static function thumbHtml($id): HtmlString
    {
        $asset = Asset::with('file')->find($id);
        $media = $asset?->file;

        if (! $media) {
            return new HtmlString(
                '<span class="text-warning-600">Selected file no longer exists in the library.</span>'
            );
        }

        $type = $media->getCustomProperty('type') ?? 'document';
        $url = e($media->getUrl());

        if ($type === 'image') {
            $thumb = in_array('thumb', array_keys($media->generated_conversions ?? []))
                ? e($media->getUrl('thumb'))
                : $url;

            return new HtmlString(
                '<img src="'.$thumb.'" alt="" style="height:64px;border-radius:8px;margin-top:4px;object-fit:cover;" />'
            );
        }

        if ($type === 'video') {
            return new HtmlString(
                '<video src="'.$url.'" style="height:64px;border-radius:8px;margin-top:4px;" muted></video>'
            );
        }

        return new HtmlString(
            '<a href="'.$url.'" target="_blank" class="text-primary-600 underline">Open '.e($media->name).'</a>'
        );
    }

    /**
     * Suffix action linking to the Media Library page (new tab) to upload.
     */
    protected static function openLibraryAction(): Action
    {
        return Action::make('openLibrary')
            ->icon('heroicon-m-photo')
            ->tooltip('Open Media Library')
            ->url(fn () => url('/lunar/media-library'), shouldOpenInNewTab: true);
    }
}
