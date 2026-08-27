<?php

namespace Tests\Feature;

use Lunar\Models\Order;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Services\InvoiceService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Lunar 1.5 made the order line purchasable morph nullable and stopped writing a
 * placeholder morph onto shipping lines: `$shippingLine->purchasable` is now
 * `null` where it used to be a `Lunar\DataTypes\ShippingOption` string that
 * resolved to nothing useful.
 *
 * Anything walking `$order->lines` therefore has to cope with a line that has no
 * purchasable. The existing InvoiceTest builds its order by hand with
 * `shipping_total => 0` and no shipping line at all, so it never exercised this.
 * Here the order is placed through the real checkout endpoints, which is the
 * only way to get a genuine shipping line.
 */
class OrderShippingLineTest extends TestCase
{
    use CreatesStorefrontData;

    /** Place a COD order with shipping through the real endpoints. */
    private function placeOrder(): Order
    {
        $product = $this->createProduct();

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->skus->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])
            ->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])
            ->assertSuccessful();

        $id = $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])
            ->assertSuccessful()
            ->json('data.id');

        return Order::with('lines')->findOrFail($id);
    }

    public function test_a_placed_order_has_a_shipping_line_with_no_purchasable(): void
    {
        $this->seedBaseData();

        $order = $this->placeOrder();

        $shipping = $order->lines->firstWhere('type', 'shipping');

        $this->assertNotNull($shipping, 'The order has no shipping line to test.');
        $this->assertNull($shipping->purchasable_type);
        $this->assertNull($shipping->purchasable_id);
        $this->assertNull($shipping->purchasable);
    }

    public function test_product_lines_excludes_the_shipping_line(): void
    {
        $this->seedBaseData();

        $order = $this->placeOrder();

        $this->assertCount(1, $order->productLines);
        $this->assertNotContains('shipping', $order->productLines->pluck('type'));

        // Every product line still resolves a purchasable — only shipping is bare.
        $this->assertNotNull($order->productLines->first()->purchasable);
    }

    /**
     * The invoice template iterates `$order->lines`, shipping line included.
     *
     * Mutation check: make invoice.blade.php read `$line->purchasable->…` and
     * this goes red.
     */
    public function test_the_invoice_pdf_renders_with_a_shipping_line(): void
    {
        $this->seedBaseData();

        $order = $this->placeOrder();

        $bytes = app(InvoiceService::class)->bytes($order);

        $this->assertStringStartsWith('%PDF', $bytes);
    }

    /** The confirmation mail walks `$order->lines` too. */
    public function test_the_confirmation_mail_renders_with_a_shipping_line(): void
    {
        $this->seedBaseData();

        $order = $this->placeOrder();

        $html = (new OrderConfirmationMail($order))->render();

        $this->assertStringContainsString($order->reference, $html);
    }
}
