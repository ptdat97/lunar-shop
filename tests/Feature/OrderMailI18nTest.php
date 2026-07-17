<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Services\OrderMailer;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Order emails render in the customer's locale (EN/VI) — subject + body — and
 * OrderMailer stamps the active storefront locale onto the queued mailable so it
 * survives the queue.
 */
class OrderMailI18nTest extends TestCase
{
    use CreatesStorefrontData;

    /** A placed order with one line + a shipping address — all a mail needs. */
    private function makeOrder(): Order
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => 'payment-received',
            'reference' => 'TEST-0001',
            'sub_total' => 5000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 5000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'type' => 'physical',
            'description' => 'Test Tee',
            'quantity' => 1,
            'unit_price' => 5000,
            'unit_quantity' => 1,
            'sub_total' => 5000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 5000,
        ]);

        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => 'Mai',
            'last_name' => 'Nguyen',
            'contact_email' => 'mai@example.com',
            'line_one' => '1 Test St',
            'city' => 'Hanoi',
        ]);

        return $order->fresh(['lines', 'shippingAddress']);
    }

    public function test_confirmation_renders_in_english(): void
    {
        $this->seedBaseData();
        $order = $this->makeOrder();

        app()->setLocale('en');
        $mail = new OrderConfirmationMail($order);

        $this->assertStringContainsString('Order confirmed', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Thank you for your order', $html);
        $this->assertStringContainsString('View your order', $html);
    }

    public function test_confirmation_renders_in_vietnamese(): void
    {
        $this->seedBaseData();
        $order = $this->makeOrder();

        app()->setLocale('vi');
        $mail = new OrderConfirmationMail($order);

        $this->assertStringContainsString('Đã xác nhận đơn hàng', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('Cảm ơn bạn đã đặt hàng', $html);
        $this->assertStringContainsString('Xem đơn hàng', $html);
    }

    public function test_mailer_stamps_active_locale_on_queued_mail(): void
    {
        $this->seedBaseData();
        $order = $this->makeOrder();

        Mail::fake();
        app()->setLocale('vi');

        app(OrderMailer::class)->send($order, new OrderConfirmationMail($order));

        Mail::assertQueued(
            OrderConfirmationMail::class,
            fn (OrderConfirmationMail $m) => $m->locale === 'vi',
        );
    }
}
