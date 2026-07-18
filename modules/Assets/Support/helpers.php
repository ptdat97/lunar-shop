<?php

use Modules\Assets\Services\MediaUrl;

/**
 * Shared media helpers. Thin wrappers over the Assets services so Blade views,
 * seeders and quick calls have a one-liner without resolving the service by
 * hand — the resolving/conversion logic still lives in modules/Assets
 * (single source), these just forward to it.
 */
if (! function_exists('media_url')) {
    /**
     * Resolve a stored image reference (Spatie media id, absolute URL, or a path
     * on the `media` disk) to a browser URL. Media ids resolve to $conversion.
     *
     * @see MediaUrl::imageUrl()
     */
    function media_url(int|string|null $value, string $conversion = 'large'): ?string
    {
        return app(MediaUrl::class)->imageUrl($value, $conversion);
    }
}
