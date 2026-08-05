<?php

namespace Modules\Assets\Filament\Forms;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Modules\Assets\Services\MediaLibraryService;

/**
 * Reusable media picker backed by the Media Library (Lunar Asset + Spatie
 * MediaLibrary). It stores the selected Lunar Asset id so the storefront can
 * resolve the original plus any conversion (thumb/webp/large/zoom…) and so a
 * file replaced in the library updates everywhere automatically.
 *
 * The underlying column stays a string (e.g. banner.image, lookbook.cover_image,
 * page.featured_image) — it holds the asset id instead of a path.
 *
 * The field renders the picked file(s) as real thumbnails and opens the library
 * in a MODAL (grid + search + type/folder filter + pagination + upload), so an
 * admin picks an image visually without leaving the form they are editing.
 *
 * Usage (unchanged — drop-in for the previous Select-based picker):
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
    public static function make(string $name, ?string $type = null, bool $multiple = false): Field
    {
        return MediaPickerField::make($name)
            ->libraryType($type)
            ->multiple($multiple)
            ->placeholder(__('admin.media.pick'))
            ->registerActions([
                static::browseAction($type, $multiple),
                static::removeAction(),
                static::moveAction(),
            ])
            ->dehydrateStateUsing(fn ($state) => $multiple
                ? array_values(array_filter((array) $state))
                : ($state ?: null));
    }

    /**
     * Modal action that opens the library browser and writes the chosen asset
     * id(s) back into the field's state.
     */
    protected static function browseAction(?string $type, bool $multiple): Action
    {
        return Action::make('browseLibrary')
            ->label(__('admin.media.browse'))
            ->icon('heroicon-m-photo')
            ->color('gray')
            ->modalHeading(__('admin.media.library'))
            ->modalSubmitActionLabel(__('admin.media.choose'))
            ->modalWidth('5xl')
            ->mountUsing(function (Set $set, Get $get) use ($multiple) {
                // Seed the modal with what the field already holds so the
                // current selection shows as selected in the grid.
                $set('browser.selected', static::idsOf($get('.'), $multiple));
                $set('browser.search', null);
                $set('browser.folder', null);
                $set('browser.page', 1);
            })
            ->form(fn () => [
                MediaBrowser::make('browser')
                    ->libraryType($type)
                    ->multiple($multiple),
            ])
            ->action(function (array $data, Set $set) use ($multiple) {
                $ids = array_values(array_filter((array) ($data['browser']['selected'] ?? [])));

                $set('.', $multiple ? $ids : ($ids[0] ?? null));
            });
    }

    /**
     * Drop one picked file from the field (the thumbnail's × control).
     */
    protected static function removeAction(): Action
    {
        return Action::make('remove')
            ->label(__('admin.media.remove'))
            ->iconButton()
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->size('sm')
            ->action(function (array $arguments, MediaPickerField $component): void {
                $component->removeId((int) ($arguments['id'] ?? 0));
            });
    }

    /**
     * Reorder a picked file within a multiple picker (the ‹ › controls).
     */
    protected static function moveAction(): Action
    {
        return Action::make('move')
            ->label(fn (array $arguments) => (int) ($arguments['offset'] ?? 0) < 0
                ? __('admin.media.move_left')
                : __('admin.media.move_right'))
            ->iconButton()
            ->icon(fn (array $arguments) => (int) ($arguments['offset'] ?? 0) < 0
                ? 'heroicon-m-arrow-left'
                : 'heroicon-m-arrow-right')
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments, MediaPickerField $component): void {
                $component->moveId(
                    (int) ($arguments['id'] ?? 0),
                    (int) ($arguments['offset'] ?? 0),
                );
            });
    }

    /**
     * Normalise a field's state into a flat list of asset ids.
     *
     * @return array<int, int>
     */
    public static function idsOf($state, bool $multiple): array
    {
        $ids = $multiple ? (array) $state : array_filter([$state]);

        return array_values(array_map(
            fn ($id) => (int) $id,
            array_filter($ids, fn ($id) => is_numeric($id)),
        ));
    }

    /**
     * Presentation payloads for the given asset ids, in the given order, with
     * ids that no longer resolve dropped.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public static function previews(array $ids): array
    {
        $library = app(MediaLibraryService::class);

        return array_values(array_filter(array_map(
            fn (int $id) => $library->preview($id),
            $ids,
        )));
    }
}
