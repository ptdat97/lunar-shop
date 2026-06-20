<?php

namespace Modules\Media\Services;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generates a single media conversion on demand. Used by the conversion route
 * so the first request for a not-yet-generated size produces it synchronously
 * (instead of 404ing or falling back to the full-size original).
 */
class ConversionGenerator
{
    public function __construct(
        protected FileManipulator $fileManipulator,
    ) {}

    /**
     * The conversion names registered for a media item.
     *
     * @return list<string>
     */
    public function conversionNames(Media $media): array
    {
        return ConversionCollection::createForMedia($media)
            ->map(fn (Conversion $c) => $c->getName())
            ->values()
            ->all();
    }

    /**
     * Resolve which registered conversion a requested filename maps to.
     * Conversion files are named "{originalStem}-{conversion}.{ext}".
     */
    public function conversionForFile(Media $media, string $filename): ?string
    {
        foreach (ConversionCollection::createForMedia($media) as $conversion) {
            if ($conversion->getConversionFile($media) === $filename) {
                return $conversion->getName();
            }
        }

        return null;
    }

    /**
     * Ensure a conversion file exists on disk, generating it if missing.
     * Returns true when the file is present (already or after generating).
     *
     * We check the actual FILE, not just the `generated_conversions` DB flag:
     * the flag can be true while the file is gone (cleared cache, failed/partial
     * generation, files wiped) — the very case we want to self-heal. When that
     * happens we force a regenerate (onlyMissing:false) instead of trusting it.
     */
    public function ensure(Media $media, string $conversion): bool
    {
        if (! in_array($conversion, $this->conversionNames($media), true)) {
            return false;
        }

        if ($this->fileExists($media, $conversion)) {
            return true;
        }

        // Generate the requested conversion SYNCHRONOUSLY. createDerivedFiles()
        // respects media-library's queue_conversions_by_default (true here), so
        // it would only dispatch a job — useless for on-demand request-time
        // generation. performConversions() runs the conversion inline instead.
        $conversions = ConversionCollection::createForMedia($media)
            ->filter(fn (Conversion $c) => $c->getName() === $conversion);

        $this->fileManipulator->performConversions($conversions, $media);

        return $this->fileExists($media->refresh(), $conversion);
    }

    /**
     * Whether the conversion file physically exists on the conversions disk.
     */
    public function fileExists(Media $media, string $conversion): bool
    {
        return Storage::disk($media->conversions_disk)
            ->exists($media->getPathRelativeToRoot($conversion));
    }
}
