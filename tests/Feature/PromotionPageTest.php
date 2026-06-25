<?php

namespace Tests\Feature;

use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Storefront promotion pages (SSR): the index lists active promotions, each has
 * its own page, and the homepage promotion-slider section renders on-sale cards.
 */
class PromotionPageTest extends TestCase
{
    use CreatesStorefrontData;

    /** A cart-wide flash sale + a product it covers. */
    private function seedFlashSale(): Discount
    {
        $this->seedBaseData();
        $this->createProduct(['name' => 'Sale Tee', 'price' => 10000]);

        $discount = Discount::create([
            'name' => 'Flash Sale — 25% Off',
            'handle' => 'flash-sale',
            'type' => \Lunar\DiscountTypes\AmountOff::class,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(2),
            'uses' => 0,
            'priority' => 100,
            'stop' => false,
            'data' => ['percentage' => 25, 'fixed_value' => false, 'flash_sale' => true],
        ]);

        foreach (Channel::all() as $channel) {
            $discount->scheduleChannel($channel, now()->subDay());
        }
        foreach (CustomerGroup::all() as $group) {
            $discount->scheduleCustomerGroup($group, now()->subDay());
        }

        return $discount;
    }

    public function test_promotions_index_lists_active_promotions(): void
    {
        $this->seedFlashSale();

        $this->get('/promotions')
            ->assertOk()
            ->assertSee('Flash Sale — 25% Off')
            ->assertSee('Sale Tee')
            ->assertSee(route('storefront.promotion', 'flash-sale'), false);
    }

    public function test_promotion_detail_page_shows_its_products(): void
    {
        $this->seedFlashSale();

        $this->get('/promotions/flash-sale')
            ->assertOk()
            ->assertSee('Flash Sale — 25% Off')
            ->assertSee('Sale Tee')
            ->assertSee('data-flash-sale', false)   // countdown hook
            ->assertSee('product-card__badge', false);
    }

    public function test_unknown_promotion_returns_404(): void
    {
        $this->seedBaseData();

        $this->get('/promotions/does-not-exist')->assertNotFound();
    }

    public function test_homepage_promotion_slider_section_renders_on_sale_products(): void
    {
        $this->seedFlashSale();

        \Modules\SectionBuilder\Models\PageSection::create([
            'page_handle' => 'home',
            'type' => 'promotion-slider',
            'sort' => 0,
            'enabled' => true,
            'settings' => ['heading' => 'On Sale Now', 'limit' => 12],
        ]);

        $html = app(\Modules\SectionBuilder\Services\SectionRenderer::class)->render('home');

        $this->assertStringContainsString('data-promotion-swiper', $html);
        $this->assertStringContainsString('On Sale Now', $html);
        $this->assertStringContainsString('Sale Tee', $html);
    }
}
