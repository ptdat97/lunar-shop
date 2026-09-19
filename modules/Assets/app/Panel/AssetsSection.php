<?php

namespace Modules\Assets\Panel;

use Closure;
use Illuminate\Support\Facades\Route;
use Lunar\Panel\Navigation\NavigationItem;
use Lunar\Panel\Navigation\NavigationRegistry;
use Lunar\Panel\Sections\Section;
use Modules\Assets\Http\Controllers\Panel\MediaLibraryController;
use Modules\Assets\Http\Controllers\Panel\MediaManagerController;

/**
 * The shop's file manager on the panel.
 *
 * Lunar's panel manages media that belongs to a record — a product's gallery, a
 * collection's thumbnail. This shop also has images that belong to nothing in
 * particular: a banner's artwork, a page's hero, a menu's mega banner, the
 * logo. Those columns hold a Lunar Asset id, and every one of them is filled
 * from here: an image field opens `picker` in an iframe and stores the id it
 * hands back.
 *
 * Routes, all under `/panel/shop/media`:
 *
 *   GET    /                        the file manager page
 *   GET    /picker                  the same manager, chrome-less, for iframes
 *   GET    /files                   list (JSON)
 *   POST   /files                   upload one file
 *   DELETE /files                   delete many   {ids}
 *   POST   /files/move              move many     {ids, folder}
 *   GET    /files/{id}              one preview
 *   PATCH  /files/{id}              name / alt / title / folder
 *   POST   /files/{id}/replace      swap the file, keep the id
 *   PATCH  /folders/{folder}        rename a folder {name}
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
        return __('admin.file_manager.title');
    }

    /**
     * Into the Content group ShopSection names, beside the banners and pages
     * whose images come from it. Core's section is registered first, so the
     * group already carries its label and position by the time this runs.
     */
    public function navigation(NavigationRegistry $registry): void
    {
        $registry->addItem('shop-content', new NavigationItem(
            key: 'media',
            label: __('admin.file_manager.title'),
            icon: 'image',
            route: 'panel.shop.media.index',
            permission: self::PERMISSION,
            priority: 90,
        ));
    }

    public function routes(): ?Closure
    {
        return function (): void {
            Route::prefix('shop/media')
                ->name('panel.shop.media.')
                ->middleware('can:'.self::PERMISSION)
                ->group(function (): void {
                    Route::get('/', [MediaManagerController::class, 'index'])->name('index');
                    Route::get('picker', [MediaManagerController::class, 'picker'])->name('picker');

                    Route::get('files', [MediaLibraryController::class, 'index'])->name('files');
                    Route::post('files', [MediaLibraryController::class, 'store'])->name('store');
                    Route::delete('files', [MediaLibraryController::class, 'destroy'])->name('destroy');
                    Route::post('files/move', [MediaLibraryController::class, 'move'])->name('move');

                    Route::get('files/{assetId}', [MediaLibraryController::class, 'show'])
                        ->whereNumber('assetId')->name('show');
                    Route::patch('files/{assetId}', [MediaLibraryController::class, 'update'])
                        ->whereNumber('assetId')->name('update');
                    Route::post('files/{assetId}/replace', [MediaLibraryController::class, 'replace'])
                        ->whereNumber('assetId')->name('replace');

                    Route::patch('folders/{folder}', [MediaLibraryController::class, 'renameFolder'])
                        ->where('folder', '[a-z0-9-]+')->name('folders.rename');
                });
        };
    }
}
