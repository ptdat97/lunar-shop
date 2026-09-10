<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Lunar\Core\Models\Order;
use Modules\Catalog\Services\ReviewService;
use Modules\Order\Mail\ReviewRequestMail;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReviewRequestService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Asking for a review, and the page that has to exist for the ask to mean
 * anything.
 *
 * The shop had a review system and nothing that ever asked — so the only
 * reviews it got were from customers motivated enough to hunt for the form.
 * Which they could not do either: reviews existed as API and panel moderation,
 * and the storefront never rendered them anywhere. An email pointing at a page
 * with no review form would have been worse than sending nothing.
 *
 * The failure modes worth pinning are the ones that cost a customer rather than
 * a review: asking twice, and asking someone who sent the goods back.
 */
class ReviewRequestTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    /** A delivered order, shipped `$daysAgo` ago. */
    private function deliveredOrder(int $daysAgo = 10): Order
    {
        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $order = Order::latest('id')->firstOrFail();

        // Anchored on the fulfilment, not the order: 2.0 dropped
        // `orders.dispatched_at` and made the lifecycle derived.
        $order->fulfilments()->update(['shipped_at' => now()->subDays($daysAgo)]);

        return $order->fresh();
    }

    private function enable(): void
    {
        config([
            'checkout.review_request_enabled' => true,
            'checkout.review_request_days' => 7,
        ]);
    }

    public function test_a_delivered_order_gets_one_request(): void
    {
        $this->seedBaseData();
        $this->enable();
        $order = $this->deliveredOrder();

        Mail::fake();
        Artisan::call('orders:request-reviews');

        Mail::assertQueued(ReviewRequestMail::class, 1);
        $this->assertNotNull($order->fresh()->review_requested_at);
    }

    public function test_a_second_run_does_not_ask_again(): void
    {
        $this->seedBaseData();
        $this->enable();
        $this->deliveredOrder();

        Artisan::call('orders:request-reviews');

        Mail::fake();
        Artisan::call('orders:request-reviews');

        Mail::assertNothingQueued();
    }

    /** Asking before it can plausibly have arrived reads as not paying attention. */
    public function test_a_recently_shipped_order_is_left_alone(): void
    {
        $this->seedBaseData();
        $this->enable();
        $this->deliveredOrder(daysAgo: 1);

        Mail::fake();
        Artisan::call('orders:request-reviews');

        Mail::assertNothingQueued();
    }

    /** Asking someone who returned the goods how they liked them loses a customer. */
    public function test_an_order_with_a_return_is_never_asked(): void
    {
        $this->seedBaseData();
        $this->enable();
        $order = $this->deliveredOrder();

        ReturnRequest::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'reference' => 'RT-TEST-1',
            'status' => ReturnRequest::REQUESTED,
            'reason' => 'too-small',
        ]);

        Mail::fake();
        Artisan::call('orders:request-reviews');

        Mail::assertNothingQueued();
    }

    public function test_nothing_is_sent_while_the_feature_is_off(): void
    {
        $this->seedBaseData();
        config(['checkout.review_request_enabled' => false]);
        $this->deliveredOrder();

        Mail::fake();
        Artisan::call('orders:request-reviews');

        Mail::assertNothingQueued();
    }

    public function test_the_dry_run_reports_without_sending_or_marking(): void
    {
        $this->seedBaseData();
        config(['checkout.review_request_enabled' => false]);
        $order = $this->deliveredOrder();

        Mail::fake();
        Artisan::call('orders:request-reviews', ['--dry-run' => true]);

        Mail::assertNothingQueued();
        $this->assertNull($order->fresh()->review_requested_at);
        $this->assertStringContainsString('[thử]', Artisan::output());
    }

    /** An email pointing at a page with no review form is worse than no email. */
    public function test_the_product_page_renders_reviews_and_a_form(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'ao-thun']);

        app(ReviewService::class)
            ->add($product->id, 'Mai', 5, 'Vải mát, lên dáng đẹp.');

        $html = $this->get(route('storefront.product', $product->defaultUrl->slug))->assertOk()->getContent();

        // The anchor the request email links to. Renaming it silently breaks
        // every link in every email already sent.
        $this->assertStringContainsString('id="danh-gia"', $html);
        $this->assertStringContainsString('Vải mát, lên dáng đẹp.', $html);
        $this->assertStringContainsString('data-review-form', $html);
    }

    /** Reading reviews must not depend on JavaScript — that is the part that sells. */
    public function test_the_review_list_is_server_rendered(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'quan-jean']);

        app(ReviewService::class)
            ->add($product->id, 'Hùng', 4, 'Đúng size.');

        $html = $this->get(route('storefront.product', $product->defaultUrl->slug))->getContent();

        $this->assertStringContainsString('Hùng', $html);
        $this->assertStringContainsString('Đúng size.', $html);
    }

    /** The service's bounds are the contract; the panel form must not outrank them. */
    public function test_the_delay_is_clamped_to_the_services_bounds(): void
    {
        $this->seedBaseData();
        config(['checkout.review_request_days' => 999]);

        $this->assertSame(
            ReviewRequestService::MAX_DELAY_DAYS,
            app(ReviewRequestService::class)->delayDays(),
        );
    }
}
