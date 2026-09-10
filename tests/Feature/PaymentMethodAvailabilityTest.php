<?php

namespace Tests\Feature;

use Modules\Checkout\Services\CheckoutService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * A gateway with no credentials must not be selectable — on the page OR the API.
 *
 * The checkout page already hid an unconfigured VNPay, but the API validation
 * whitelist was a separate, unfiltered list. So `payment_type: vnpay` was
 * accepted on a shop that cannot take VNPay: the order was placed, no redirect
 * was produced (paymentRedirectUrl returns null when unconfigured), and the
 * shopper landed on the confirmation page having paid nothing while the stock
 * stayed committed until the abandoned-order sweep.
 */
class PaymentMethodAvailabilityTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_an_unconfigured_gateway_is_not_offered(): void
    {
        $this->seedBaseData();

        $methods = app(CheckoutService::class)->paymentMethods();

        $this->assertContains('cod', $methods, 'COD không cần credential, phải luôn có.');
        $this->assertContains('bank-transfer', $methods);
        $this->assertNotContains('vnpay', $methods, 'VNPay chưa cấu hình mà vẫn được chào.');
        $this->assertNotContains('momo', $methods, 'MoMo chưa cấu hình mà vẫn được chào.');
    }

    public function test_a_configured_gateway_is_offered(): void
    {
        $this->seedBaseData();

        config([
            'payment.vnpay.tmn_code' => 'TESTCODE',
            'payment.vnpay.hash_secret' => 'secret',
        ]);

        $this->assertContains('vnpay', app(CheckoutService::class)->paymentMethods());
    }

    /** The page and the API must never disagree about which gateways are on. */
    public function test_the_page_and_the_api_agree(): void
    {
        $this->seedBaseData();

        $checkout = app(CheckoutService::class);

        foreach ([false, true] as $configured) {
            if ($configured) {
                config(['payment.momo.partner_code' => 'MOMOTEST']);
            }

            $context = $checkout->paymentContext();
            $methods = $checkout->paymentMethods();

            $this->assertSame(
                $context['momoEnabled'],
                in_array('momo', $methods, true),
                'Trang checkout và validation API bất đồng về MoMo.',
            );
        }
    }

    /** The API must reject a method the shop cannot actually take money with. */
    public function test_the_api_refuses_an_unconfigured_gateway(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'vnpay'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_type');
    }
}
