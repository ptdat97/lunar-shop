<?php

namespace Modules\Catalog\Panel;

use Closure;
use Illuminate\Support\Facades\Route;
use Lunar\Panel\Sections\Section;
use Lunar\Panel\Slots\Slot;
use Lunar\Panel\Slots\SlotRegistry;
use Modules\Catalog\Http\Controllers\Panel\ProductSizingController;

/**
 * The Catalog module's addition to Lunar's own product editor.
 *
 * Lunar 2.0's product screen is first-party and better than anything this shop
 * would write, so it is left alone; what it cannot know about is this shop's
 * size charts and fabric/care rows. The panel's slot registry exists for
 * exactly that — a component injected into a named zone of a first-party page,
 * receiving that page's record as a prop.
 *
 * Nothing here overrides a panel screen. If this section were removed the
 * product editor would carry on working, minus one sidebar card.
 */
class CatalogSection extends Section
{
    public const PERMISSION = 'catalog:manage-products';

    public function key(): string
    {
        return 'shop-catalog';
    }

    public function label(): string
    {
        return __('admin.sizing.title');
    }

    public function slots(SlotRegistry $registry): void
    {
        $registry->add(new Slot(
            // "{page}:{region}:{position}" — the page id is the panel route
            // name without its `panel.` prefix.
            zone: 'products.edit:sidebar:after',
            component: 'shop::ProductSizing',
            props: ProductSizingController::slotProps(),
            permission: self::PERMISSION,
        ));
    }

    public function routes(): ?Closure
    {
        return function (): void {
            Route::middleware('can:'.self::PERMISSION)->group(function (): void {
                Route::get('shop/products/{product}/sizing', [ProductSizingController::class, 'show'])
                    ->name('panel.shop.products.sizing.show');
                Route::put('shop/products/{product}/sizing', [ProductSizingController::class, 'update'])
                    ->name('panel.shop.products.sizing.update');
            });
        };
    }
}
