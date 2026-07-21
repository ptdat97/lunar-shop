<?php

namespace Modules\Assets\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Assets\Services\ConversionGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a media conversion whose FILE IS MISSING by generating it on the spot.
 *
 * The web server (or `php artisan serve`) serves existing files under
 * public/media statically, so this route only ever fires for a conversion that
 * hasn't been generated yet — the browser's own image request becomes the
 * trigger. That makes image delivery independent of Horizon: with no worker
 * running, each <img> request generates exactly its own size (lock-guarded,
 * parallel across images) and every later request is a static file hit.
 */
class MediaConversionController extends Controller
{
    public function __construct(
        protected ConversionGenerator $generator,
    ) {}

    /**
     * GET /media/{media}/conversions/{filename}
     */
    public function __invoke(int $mediaId, string $filename): StreamedResponse|RedirectResponse
    {
        // No SubstituteBindings middleware on this route (kept middleware-free
        // for throughput), so resolve the model manually.
        $media = Media::query()->find($mediaId);

        abort_if(! $media, 404);

        // Map the requested filename back to a registered conversion name; the
        // file on disk is then addressed via the media's own path generator, so
        // the raw filename never touches the filesystem (no traversal surface).
        $conversion = $this->generator->conversionForFile($media, $filename);

        abort_if(! $conversion, 404);

        if (! $this->generator->ensure($media, $conversion)) {
            // Generation failed (unreadable/corrupt source) — show the original
            // instead of a broken image. Not cached, so a repaired source heals.
            return redirect($media->getUrl());
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($media->conversions_disk);

        // Far-future caching: after this one dynamic hit the file exists, so
        // every later request is served statically by the web server anyway.
        return $disk->response(
            $media->getPathRelativeToRoot($conversion),
            $filename,
            ['Cache-Control' => 'public, max-age=31536000, immutable'],
        );
    }
}
