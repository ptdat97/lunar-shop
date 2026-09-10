<?php

namespace Tests\Feature;

use Lunar\Core\Models\Staff;
use Lunar\Panel\PanelManager;
use Tests\TestCase;

/**
 * The sidebar has to read like the shop's day.
 *
 * Before this, every resource the project declares landed in one flat group
 * called "Nội dung shop", and that group sat ABOVE Lunar's own Catalog and
 * Sales. So the first thing in the panel was the scheduler log — a read-only
 * diagnostic — and the returns queue sat between the redirect table and the
 * shipping zones. Orders, the one screen a shop opens every morning, was third.
 *
 * The order is asserted, not the mechanism, for a specific reason: the piece
 * that puts Sales above Catalog (NavigationOrder) works by naming Lunar's
 * groups before Lunar does, which depends on Lunar processing `dashboard`
 * before `catalog` and `sales`. If a future release reorders its sections that
 * call quietly becomes a no-op — nothing breaks, the sidebar just drifts back.
 * This test is what turns that silent drift into a red build.
 */
class PanelNavigationOrderTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function navGroups(): array
    {
        $staff = Staff::factory()->create(['admin' => true]);

        return app(PanelManager::class)->navigation()->toArray($staff)['groups'] ?? [];
    }

    public function test_the_groups_run_from_daily_work_to_diagnostics(): void
    {
        $keys = array_column($this->navGroups(), 'key');

        $this->assertSame(
            ['sales', 'catalog', 'shop-operations', 'shop-content', 'shop-system'],
            $keys,
            'Thứ tự nhóm sidebar đã lệch khỏi thiết kế.',
        );
    }

    /** Orders is the screen a shop opens every morning. */
    public function test_sales_sits_above_catalog(): void
    {
        $keys = array_column($this->navGroups(), 'key');

        $this->assertLessThan(
            array_search('catalog', $keys, true),
            array_search('sales', $keys, true),
            'Bán hàng phải nằm trên Danh mục — nếu đỏ, xem NavigationOrder: '
                .'nhiều khả năng Lunar đã đổi thứ tự xử lý section nên nó thành no-op.',
        );
    }

    /** Returns are order work, not content. */
    public function test_returns_sit_with_orders(): void
    {
        $sales = collect($this->navGroups())->firstWhere('key', 'sales');

        $this->assertNotNull($sales);

        $keys = array_column($sales['items'], 'key');

        $this->assertContains('returns', $keys, 'Đổi/trả phải nằm trong nhóm Bán hàng.');
        $this->assertLessThan(
            array_search('customers', $keys, true),
            array_search('returns', $keys, true),
            'Đổi/trả phải đứng ngay dưới Orders, trên Khách hàng.',
        );
    }

    /** Reviews and size charts are product work. */
    public function test_product_data_sits_with_the_catalogue(): void
    {
        $catalog = collect($this->navGroups())->firstWhere('key', 'catalog');

        $this->assertNotNull($catalog);

        $keys = array_column($catalog['items'], 'key');

        $this->assertContains('reviews', $keys);
        $this->assertContains('size-charts', $keys);

        // Below Lunar's own catalogue screens, which all sit at priority 50.
        $this->assertGreaterThan(
            array_search('products', $keys, true),
            array_search('reviews', $keys, true),
        );
    }

    /**
     * The scheduler log used to be the first item in the whole sidebar.
     * It is read-only, and nobody opens it unless something is already wrong.
     */
    public function test_the_scheduler_log_is_last(): void
    {
        $groups = $this->navGroups();

        $this->assertSame('shop-system', end($groups)['key']);
        $this->assertSame('scheduled-runs', $groups[count($groups) - 1]['items'][0]['key']);
    }

    /** A group with no visible items must not leave an empty heading behind. */
    public function test_no_group_renders_empty(): void
    {
        foreach ($this->navGroups() as $group) {
            $this->assertNotEmpty($group['items'], "Nhóm [{$group['key']}] rỗng mà vẫn hiện.");
        }
    }
}
