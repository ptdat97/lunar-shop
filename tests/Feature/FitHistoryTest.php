<?php

namespace Tests\Feature;

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\TaxClass;
use Modules\Catalog\Models\ProductSku;
use Modules\Catalog\Services\FitHistoryService;
use Modules\Order\Models\ReturnRequest;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Size Intelligence v2: infer the shopper's real size from what they kept vs
 * what they returned, and detect the "between two sizes" case.
 */
class FitHistoryTest extends TestCase
{
    use CreatesStorefrontData;

    /** @var array<string, ProductSku> size label => variant */
    private array $variants = [];

    private Product $product;

    private Customer $customer;

    /** Build a product with one SKU per chart size, via the flexible Size axis. */
    private function makeSizedProduct(): void
    {
        $sizes = ['S', 'M', 'L'];

        // A single "Size" variable whose values are S/M/L. A SKU picks a size by
        // its index into this variable (variant_indexes = [i]).
        $this->product = $this->attachSizeChart($this->createProduct([
            'variables' => [[
                'name' => ['en' => 'Size'],
                'values' => array_map(fn ($s) => ['name' => ['en' => $s], 'image' => ''], $sizes),
                'isImage' => false,
            ]],
        ]));

        // createProduct already made a default SKU; repurpose the product to hold
        // exactly one SKU per size.
        $this->product->skus()->forceDelete();

        foreach ($sizes as $i => $size) {
            $this->variants[$size] = ProductSku::create([
                'product_id' => $this->product->id,
                'sku' => 'FIT-'.$size.'-'.uniqid(),
                'variants' => [$i],
                'quantity' => 10,
                'is_default' => $i === 0,
                'status' => 'published',
                'tax_class_id' => TaxClass::getDefault()?->id,
            ]);
        }

        $this->customer = Customer::factory()->create();
    }

    /** A paid order for one size; returns the created order line. */
    private function buy(string $size, string $status = 'payment-received'): OrderLine
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ]);

        return OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => 'product_sku',
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

    private function fit(): ?array
    {
        return app(FitHistoryService::class)->for($this->customer->fresh(), $this->product->fresh());
    }

    public function test_no_history_yields_no_signal(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->assertNull($this->fit());
    }

    public function test_a_kept_size_is_the_recommendation(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->buy('M');

        $fit = $this->fit();

        $this->assertSame('M', $fit['recommended']);
        $this->assertSame(['M'], $fit['kept']);
        $this->assertSame('usual_size', $fit['advice']);
        $this->assertNull($fit['between']);
    }

    public function test_unpaid_orders_are_ignored(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->buy('M', 'awaiting-payment');

        $this->assertNull($this->fit());
    }

    public function test_returning_too_small_recommends_one_size_up(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->returnLine($this->buy('S'), FitHistoryService::REASON_TOO_SMALL);

        $fit = $this->fit();

        $this->assertSame('M', $fit['recommended']);
        $this->assertSame([], $fit['kept']);
        $this->assertSame(['S' => 'too-small'], $fit['returned']);
    }

    public function test_returning_too_large_recommends_one_size_down(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->returnLine($this->buy('L'), FitHistoryService::REASON_TOO_LARGE);

        $this->assertSame('M', $this->fit()['recommended']);
    }

    public function test_between_two_sizes_warns_instead_of_recommending(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        // M too small, L too large → nothing on this chart fits.
        $this->returnLine($this->buy('M'), FitHistoryService::REASON_TOO_SMALL);
        $this->returnLine($this->buy('L'), FitHistoryService::REASON_TOO_LARGE);

        $fit = $this->fit();

        $this->assertSame(['M', 'L'], $fit['between']);
        $this->assertSame('between_sizes', $fit['advice']);
        $this->assertNull($fit['recommended']);
    }

    public function test_keeping_a_size_outweighs_an_earlier_return_of_it(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->returnLine($this->buy('M'), FitHistoryService::REASON_TOO_SMALL);
        $this->buy('M'); // bought again, kept it

        $fit = $this->fit();

        $this->assertSame('M', $fit['recommended']);
        $this->assertSame(['M'], $fit['kept']);
        $this->assertSame([], $fit['returned']);
    }

    public function test_rejected_returns_do_not_count_as_a_bad_fit(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->returnLine($this->buy('M'), FitHistoryService::REASON_TOO_SMALL, ReturnRequest::REJECTED);

        // Staff rejected the return, so the customer kept the M.
        $this->assertSame('M', $this->fit()['recommended']);
    }

    public function test_contradictory_returns_on_one_size_yield_no_recommendation(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->returnLine($this->buy('M'), FitHistoryService::REASON_TOO_SMALL);
        $this->returnLine($this->buy('M'), FitHistoryService::REASON_TOO_LARGE);

        $fit = $this->fit();

        // Never kept, so M must not be recommended, and the conflicting
        // directions cancel rather than pointing at S or L.
        $this->assertNull($fit['recommended']);
        $this->assertSame([], $fit['kept']);
        $this->assertSame(['M' => 'wrong-size'], $fit['returned']);
    }

    public function test_legacy_wrong_size_reason_gives_no_direction(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->returnLine($this->buy('M'), FitHistoryService::REASON_WRONG_SIZE);

        $this->assertNull($this->fit()['recommended']);
    }

    public function test_another_customers_history_is_not_leaked(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $this->buy('M');

        $other = Customer::factory()->create();
        $this->assertNull(app(FitHistoryService::class)->for($other, $this->product->fresh()));
    }

    public function test_product_without_a_size_chart_yields_no_signal(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M');

        $this->product->sizeChart()->sync([]); // drop the chart
        $this->product = $this->product->fresh();

        $this->assertNull($this->fit());
    }

    /** The recommend-size endpoint for this product. */
    private function recommendUrl(): string
    {
        return '/api/v1/products/'.$this->product->defaultUrl->slug.'/recommend-size';
    }

    /** @var array<string,int> a body that maps onto the S/M/L fixture chart */
    private const BODY = ['bust' => 88, 'waist' => 70, 'hip' => 94];

    public function test_recommend_size_omits_fit_history_for_guests(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('M');

        // Public endpoint: a guest still gets a measurement result, never a 401.
        $this->postJson($this->recommendUrl(), self::BODY)
            ->assertOk()
            ->assertJsonPath('data.fit_history', null)
            ->assertJsonPath('data.recommended.size', 'M');
    }

    public function test_recommend_size_includes_fit_history_for_cookie_session(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->returnLine($this->buy('S'), FitHistoryService::REASON_TOO_SMALL);

        $user = $this->createUser();
        $user->customers()->attach($this->customer->id);

        $this->actingAs($user)
            ->postJson($this->recommendUrl(), self::BODY)
            ->assertOk()
            ->assertJsonPath('data.fit_history.recommended', 'M')
            ->assertJsonPath('data.fit_history.returned.S', 'too-small');
    }

    public function test_recommend_size_includes_fit_history_for_bearer_token(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();
        $this->buy('L');

        $user = $this->createUser();
        $user->customers()->attach($this->customer->id);
        $token = $user->createToken('test')->plainTextToken;

        // No cookie/session — purely the Bearer token (headless client).
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson($this->recommendUrl(), self::BODY)
            ->assertOk()
            ->assertJsonPath('data.fit_history.kept', ['L']);
    }

    public function test_recommend_size_omits_fit_history_when_user_has_no_history(): void
    {
        $this->seedBaseData();
        $this->makeSizedProduct();

        $user = $this->createUser();
        $user->customers()->attach($this->customer->id);

        $this->actingAs($user)
            ->postJson($this->recommendUrl(), self::BODY)
            ->assertOk()
            ->assertJsonPath('data.fit_history', null);
    }
}
