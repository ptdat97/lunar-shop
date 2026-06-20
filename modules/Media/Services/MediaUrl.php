<?php

namespace Modules\Media\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves a media conversion URL, generating the conversion on demand when its
 * file is missing. Single entry point used by API Resources + Blade so every
 * image URL self-heals: a not-yet-generated (or wiped) size is produced the
 * first time it's requested, then served as a static file thereafter.
 */
class MediaUrl
{
    public function __construct(
        protected ConversionGenerator $generator,
    ) {}

    /**
     * URL for a conversion, generating it if the file is missing. Falls back to
     * the original when the conversion can't be produced (e.g. unknown name).
     */
    public function conversion(?Media $media, string $conversion): ?string
    {
        if (! $media) {
            return null;
        }

        if ($this->generator->ensure($media, $conversion)) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }
}
