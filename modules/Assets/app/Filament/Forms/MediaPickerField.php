<?php

namespace Modules\Assets\Filament\Forms;

use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

/**
 * The visual half of {@see MediaPicker}: renders the currently-picked library
 * file(s) as thumbnails with remove (and, for multiple, reorder) controls, plus
 * the "browse library" button that opens the modal browser.
 *
 * State is exactly what the picker stores — a single Asset id, or an array of
 * Asset ids when `multiple()`. Nothing here writes to the library; it only
 * displays and reorders references.
 */
class MediaPickerField extends Field
{
    use HasPlaceholder;

    protected string $view = 'assets::filament.forms.media-picker';

    /** Restrict the browser to one logical library type (image|video|document). */
    protected ?string $libraryType = null;

    /** Store an array of asset ids instead of a single id. */
    protected bool $isMultiple = false;

    public function libraryType(?string $type): static
    {
        $this->libraryType = $type;

        return $this;
    }

    public function getLibraryType(): ?string
    {
        return $this->libraryType;
    }

    public function multiple(bool $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    /**
     * Presentation payloads for the picked file(s), in stored order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPreviews(): array
    {
        return MediaPicker::previews(
            MediaPicker::idsOf($this->getState(), $this->isMultiple),
        );
    }

    /**
     * Remove one picked asset id from the state (single: clears the field).
     */
    public function removeId(int $id): void
    {
        if (! $this->isMultiple) {
            $this->state(null);

            return;
        }

        $this->state(array_values(array_filter(
            MediaPicker::idsOf($this->getState(), true),
            fn (int $existing) => $existing !== $id,
        )));
    }

    /**
     * Move a picked asset one slot earlier/later — the order the storefront
     * renders them in (gallery order, payment-badge order).
     */
    public function moveId(int $id, int $offset): void
    {
        if (! $this->isMultiple) {
            return;
        }

        $ids = MediaPicker::idsOf($this->getState(), true);
        $from = array_search($id, $ids, true);

        if ($from === false) {
            return;
        }

        $to = $from + $offset;

        if ($to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        $this->state(array_values($ids));
    }
}
