<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Lunar\Core\Models\Order;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Who can read an order's confirmation page, and which orders have one.
 *
 * Two holes, both found by reading Lunar's own checkout guide against this
 * code:
 *
 * 1. The page was keyed on `reference`, which Lunar generates as the
 *    zero-padded primary key — `00000001`, `00000002`, … So anyone could count
 *    upwards and read every order the shop had ever taken: the products, the
 *    quantities, and every money total. Lunar already mints `public_id`, a
 *    ULID its own docblock calls "the outward-facing handle".
 *
 * 2. Nothing checked `placed_at`. The guide is explicit that an order is only
 *    placed once that column has a value, and both gateway drivers create a
 *    DRAFT order before redirecting the shopper away. A shopper who abandons
 *    VNPay leaves a draft behind; showing it as "thank you for your order"
 *    tells someone they bought something they did not.
 */
class CheckoutConfirmationAccessTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private function placeOrder(): Order
    {
        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        return Order::latest('id')->firstOrFail();
    }

    public function test_the_buyer_can_open_their_confirmation(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $this->get(route('storefront.checkout.confirmation', $order->public_id))
            ->assertOk()
            // The human-facing number still shows — it is what a customer
            // quotes to support. It just is not what the URL is keyed on.
            ->assertSee($order->reference);
    }

    /** The whole point: counting upwards must reach nothing. */
    public function test_the_sequential_reference_no_longer_opens_an_order(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $this->assertMatchesRegularExpression(
            '/^\d+$/',
            (string) $order->reference,
            'Reference không còn là số thuần — kiểm lại giả định của test này.',
        );

        $this->get('/checkout/confirmation/'.$order->reference)->assertNotFound();
    }

    public function test_an_unknown_handle_is_a_404(): void
    {
        $this->seedBaseData();

        $this->get('/checkout/confirmation/01JQKHONGCOTHAT0000000000')->assertNotFound();
    }

    /**
     * A draft is what a gateway leaves behind when the shopper walks away
     * mid-payment. It is not a purchase.
     */
    public function test_a_draft_order_has_no_confirmation_page(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        Order::withoutTimestamps(
            fn () => $order->forceFill(['placed_at' => null])->saveQuietly(),
        );

        $this->get(route('storefront.checkout.confirmation', $order->public_id))
            ->assertNotFound();
    }

    /** public_id must be unguessable — a ULID, not a counter. */
    public function test_the_handle_is_not_derived_from_the_id(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        $this->assertTrue(
            Str::isUlid((string) $order->public_id),
            'public_id phải là ULID.',
        );

        // Không khẳng định "id không xuất hiện trong chuỗi": ULID dài 26 ký tự
        // nên một id một chữ số gần như chắc chắn có mặt ngẫu nhiên — phép kiểm
        // đó đỏ oan chứ không bắt được gì.
        //
        // Thứ thật sự cần: hai đơn liên tiếp phải cho ra hai handle KHÔNG liên
        // tiếp, tức là đoán từ cái này ra cái kia không được.
        $second = $this->placeOrder();

        $this->assertNotSame($order->public_id, $second->public_id);
        $this->assertSame(
            1,
            $second->id - $order->id,
            'Hai đơn phải liền id thì phép so sánh dưới mới có nghĩa.',
        );
        $this->assertGreaterThan(
            1,
            levenshtein((string) $order->public_id, (string) $second->public_id),
            'Hai handle của hai đơn liền nhau chỉ khác một ký tự — vẫn đoán được.',
        );
    }
}
