<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Catalog\Models\SizeChart;
use Modules\Inventory\Models\StockNotification;
use Modules\Shipping\Models\ShippingZone;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The operational screens the panel does not ship first-party: shipping zones,
 * size charts, and the back-in-stock queue.
 */
class PanelOperationsResourceTest extends TestCase
{
    use CreatesStorefrontData;

    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    /**
     * `states` is a JSON array of province names, edited as one comma-separated
     * line. What matters is that it lands in the column as a list — the zone
     * matcher does an array lookup on it.
     */
    public function test_shipping_zone_provinces_round_trip_as_a_list(): void
    {
        $this->actingAsAdmin()
            ->post(route('panel.shop.shipping-zones.store'), [
                'name' => 'Nội thành Hà Nội',
                'country_code' => 'VN',
                'states' => 'Hà Nội, Hưng Yên ,  ',
                'rate' => 25000,
                'free_threshold' => 500000,
                'priority' => 10,
                'enabled' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $zone = ShippingZone::firstWhere('name', 'Nội thành Hà Nội');

        // Trimmed, and the trailing empty entry dropped.
        $this->assertSame(['Hà Nội', 'Hưng Yên'], $zone->states);
        $this->assertSame(25000, $zone->rate);

        $this->actingAsAdmin()
            ->get(route('panel.shop.shipping-zones.edit', $zone->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('record.states', 'Hà Nội, Hưng Yên'),
            );
    }

    /** Zones are listed the way the matcher walks them: priority first. */
    public function test_shipping_zones_list_highest_priority_first(): void
    {
        ShippingZone::create(['name' => 'Toàn quốc', 'country_code' => 'VN', 'rate' => 35000, 'priority' => 0, 'enabled' => true]);
        ShippingZone::create(['name' => 'Nội thành', 'country_code' => 'VN', 'rate' => 20000, 'priority' => 10, 'enabled' => true]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.shipping-zones.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.name', 'Nội thành')
                ->where('rows.1.name', 'Toàn quốc'),
            );
    }

    public function test_size_chart_rows_are_saved_in_order(): void
    {
        $chart = SizeChart::create(['name' => 'Áo nữ', 'category' => 'tops', 'active' => true]);

        $this->actingAsAdmin()
            ->put(route('panel.shop.size-charts.update', $chart->id), [
                'name' => 'Áo nữ',
                'category' => 'tops',
                'active' => true,
                'rows' => [
                    ['size' => 'S', 'fit' => 'regular', 'bust' => '82-86', 'waist' => '64'],
                    ['size' => 'M', 'fit' => 'regular', 'bust' => '86-90', 'waist' => '68'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $rows = $chart->fresh()->rows;

        $this->assertSame(['S', 'M'], $rows->pluck('size')->all());
        $this->assertSame([0, 1], $rows->pluck('sort')->all());
        // A range is stored verbatim; the recommender reduces it to a mid-point.
        $this->assertSame(84.0, $rows->first()->numeric('bust'));
    }

    /** The back-in-stock queue is filled by customers; staff only look. */
    public function test_the_stock_queue_is_read_only_and_shows_its_status(): void
    {
        $product = $this->createProduct();
        $variant = $product->variants->first();

        StockNotification::create(['product_variant_id' => $variant->id, 'email' => 'mai@example.com']);
        StockNotification::create([
            'product_variant_id' => $variant->id,
            'email' => 'lan@example.com',
            'notified_at' => now(),
        ]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.stock-notifications.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('resource.canCreate', false)
                ->where('resource.canEdit', false)
                ->has('rows', 2)
                ->where('rows.0.status', __('admin.stock_notifications.status_notified'))
                ->where('rows.1.status', __('admin.stock_notifications.status_waiting'))
                ->where('rows.0.sku', $variant->sku),
            );

        $this->actingAsAdmin()
            ->get(route('panel.shop.stock-notifications.create'))
            ->assertNotFound();
    }

    /**
     * The queue joins each row to its variant and product. Without the eager
     * load that is two queries per row, on the table that grows fastest here.
     */
    public function test_the_stock_queue_does_not_query_per_row(): void
    {
        $product = $this->createProduct();
        $variant = $product->variants->first();

        foreach (range(1, 8) as $i) {
            StockNotification::create([
                'product_variant_id' => $variant->id,
                'email' => "kh{$i}@example.com",
            ]);
        }

        $this->actingAsAdmin();

        \DB::enableQueryLog();

        $this->get(route('panel.shop.stock-notifications.index'))->assertOk();

        $queries = count(\DB::getQueryLog());

        \DB::disableQueryLog();

        $this->assertLessThan(
            25,
            $queries,
            "Bảng này chạy {$queries} query cho 8 dòng — nhiều khả năng eager load đã rơi.",
        );
    }
}
