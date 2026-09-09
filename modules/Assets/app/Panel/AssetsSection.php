<?php

namespace Modules\Assets\Panel;

use Closure;
use Illuminate\Support\Facades\Route;
use Lunar\Panel\Sections\Section;
use Modules\Assets\Http\Controllers\Panel\MediaBrowserController;

/**
 * The media library the panel's image fields pick from.
 *
 * Lunar's panel manages media that belongs to a record — a product's gallery, a
 * collection's thumbnail. This shop also has images that belong to nothing in
 * particular: a banner's artwork, a page's hero, a menu's mega banner. Those
 * columns hold a Lunar Asset id, so a picker needs a library to browse.
 *
 * No navigation entry: the library is only ever reached from inside a form.
 */
class AssetsSection extends Section
{
    public const PERMISSION = 'content:manage';

    public function key(): string
    {
        return 'shop-media';
    }

    public function label(): string
    {
        return __('admin.media.pick');
    }

    public function routes(): ?Closure
    {
        return function (): void {
            Route::prefix('shop/media')
                ->name('panel.shop.media.')
                ->middleware('can:'.self::PERMISSION)
                ->group(function (): void {
                    Route::get('/', [MediaBrowserController::class, 'index'])->name('index');
                    Route::post('/', [MediaBrowserController::class, 'store'])->name('store');
                    // `{assetId}`, not `{asset}`: Lunar binds an Asset model
                    // to that name, and this endpoint has to answer for an id
                    // whose asset is gone — a row can still hold one.
                    Route::get('/{assetId}', [MediaBrowserController::class, 'show'])
                        ->whereNumber('assetId')->name('show');
                });
        };
    }
}
