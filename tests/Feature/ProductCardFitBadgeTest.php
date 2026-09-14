<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Modules\Catalog\Models\SizeChart;
use Modules\Catalog\Services\FitHistoryService;
use Modules\Order\Models\ReturnRequest;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * "Your size" badge on product cards (docs/roadmap.md §14).
 *
 * The badge is the CONSERVATIVE grid rule: only a size the shopper actually
 * bought and kept. Predictions (stepping off a return) and between-sizes
 * warnings stay on the product page where there is room to explain them — a
 * wrong badge on a grid is wrong for a whole grid at once.
 *
 * It must also be cheap: a grid of cards must cost a flat number of queries,
 * not one per card.
 */
class ProductCardFitBadgeTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private Product $product;

    private Customer $customer;

    /** @var array<string, ProductVariant> size label => variant */
    private array $variants = [];

    /** Build a product with one variant per size, on an optional shared chart. */
    private function makeSizedProduct(?SizeChart $chart = null): void
    {
        $this->product = $this->createProduct();

        $this->product->sizeChart()->sync([($chart ?? $this->newChart())->id]);

        $this->product->variants()->delete();

        foreach (['S', 'M', 'L'] as $size) {
            $value = $this->optionValue($this->product, 'Size', $size);

            $variant = ProductVariant::create([
                'product_id' => $this->product->id,
                'sku' => 'BADGE-'.$size.'-'.uniqid(),
                'enabled' => true,
                'tax_class_id' => TaxClass::getDefault()?->id,
            ]);

            $variant->values()->syncWithoutDetaching([$value->id]);

            $this->variants[$size] = $variant;
        }

        $this->product->load('productOptions');

        $this->customer = Customer::factory()->create();
    }

    /** A reusable S/M/L size chart (charts are shared across products in the shop). */
    private function newChart(): SizeChart
    {
        $chart = SizeChart::create(['name' => 'Tops', 'category' => 'tops', 'active' => true]);

        foreach (['S' => 82, 'M' => 88, 'L' => 94] as $size => $bust) {
            $chart->rows()->create([
                'size' => $size, 'fit' => 'regular',
                'bust' => $bust, 'waist' => 64, 'hip' => 88,
            ]);
        }

        return $chart;
    }

    /** A paid order for one size; returns the created order line. */
    private function buy(string $size, string $status = 'payment-received'): OrderLine
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $this->customer->id,
            ...$this->orderAttributesFor($status),
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ]);

        return OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => 'product_variant',
            'purchasable_id' => $this->variants[$size]->id,
            'type' => 'physical',
            'description' => 'Tee '.$size,
            'quantity' => 1, 'unit_price' => 1000, 'unit_quantity' => 1,
            'sub_total' => 1000, 'discount_total' => 0, 'tax_total' => 0, 'total' => 1000,
        ]);
    }

    /** Open a return against an order line with a size reason. */
    private function returnLine(OrderLine $line, string $reason, string $status = ReturnRequest::APPROVED): void
    {
        $request = ReturnRequest::create([
            'order_id' => $line->order_id,
            'customer_id' => $this->customer->id,
            'reference' => 'RMA-'.uniqid(),
            'status' => $status,
            'reason' => $reason,
        ]);

        $request->lines()->create(['order_line_id' => $line->id, 'quantity' => 1]);
    }

    /** A user linked to the customer, signed in. */
    private function signedInShopper(): User
    {
        $user = $this->createUser();
        $user->customers()->attach($this->customer->id);

        return $user;
    }

    public function test_a_kept_size_becomes_a_badge_on_the_grid_json(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M');

        $this->actingAs($this->signedInShopper())
            ->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonPath('data.0.fit_size', 'M');
    }

    public function test_the_badge_renders_in_the_ssr_grid(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M');

        $html = $this->actingAs($this->signedInShopper())
            ->get('/search')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('product-card__fit-size', $html);
        $this->assertStringContainsString('Your size: M', $html);
    }

    public function test_guests_get_no_badge(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M');

        $this->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonPath('data.0.fit_size', null);
    }

    public function test_a_user_without_a_linked_customer_gets_no_badge(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M');

        // Authenticated but not linked to the customer that kept the size.
        $this->actingAs($this->createUser())
            ->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonPath('data.0.fit_size', null);
    }

    public function test_a_kept_size_wins_over_an_earlier_return_of_it(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->returnLine($this->buy('M'), FitHistoryService::REASON_TOO_SMALL);
        $this->buy('M'); // bought again, kept it

        $this->actingAs($this->signedInShopper())
            ->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonPath('data.0.fit_size', 'M');
    }

    public function test_a_return_prediction_does_not_become_a_badge(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->returnLine($this->buy('S'), FitHistoryService::REASON_TOO_SMALL);

        // The product page would step up to M, but a grid badge must not guess:
        // nothing here was kept, so there is no badge.
        $this->actingAs($this->signedInShopper())
            ->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonPath('data.0.fit_size', null);
    }

    public function test_another_shoppers_history_is_never_leaked(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M'); // shopper A keeps M

        $shopperA = $this->customer;

        // A second shopper buys the same product in a DIFFERENT size.
        $this->customer = Customer::factory()->create();
        $this->buy('L');

        $user = $this->createUser();
        $user->customers()->attach($shopperA->id);

        // Shopper A must still only see their own kept size, not B's.
        $this->actingAs($user)
            ->getJson('/api/v1/products?per_page=24')
            ->assertOk()
            ->assertJsonPath('data.0.fit_size', 'M');
    }

    public function test_a_grids_badges_cost_flat_queries_not_per_card(): void
    {
        $this->seedBaseData();

        $customer = Customer::factory()->create();
        $chart = $this->newChart();

        // Eight products, all sharing ONE chart, each with a kept 'M' — the
        // worst case for a badge grid.
        foreach (range(1, 8) as $i) {
            $this->makeSizedProduct($chart);
            $this->customer = $customer;
            $this->buy('M');
        }

        $user = $this->createUser();
        $user->customers()->attach($customer->id);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->getJson('/api/v1/products?per_page=24')->assertOk();

        // Only the badge path touches these tables; the search grid does not.
        $badgeQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'lunar_order_lines')
                || str_contains($q['query'], 'size_chart'))
            ->count();

        DB::disableQueryLog();

        // One history query + one chart pivot + one chart rows = 3, shared by
        // all eight cards. A flat bound (not ≥8) proves there is no N+1.
        $this->assertLessThanOrEqual(4, $badgeQueries, "8-card grid ran {$badgeQueries} badge queries — per-card N+1.");
    }
}
