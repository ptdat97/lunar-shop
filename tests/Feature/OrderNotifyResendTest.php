<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Lunar\Core\Contracts\Actions\Orders\NotifiesCustomer;
use Lunar\Core\Contracts\OrderNotificationManifest;
use Lunar\Core\Models\Order;
use Modules\Order\Notifications\OrderConfirmationNotification;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * The panel's "notify customer" dialog must be able to resend the shop's own
 * order emails, not just Lunar's generic placeholder.
 *
 * The dialog builds whatever it finds in Lunar's OrderNotificationManifest.
 * That catalogue ships with one entry (`order-update`), and our real order
 * emails are Mailables fired from event listeners — so until they were
 * registered, staff could send a customer a blank "order update" but could not
 * resend the confirmation the customer was asking about. Classic shape of this
 * project's recurring bug: what we write and what the panel reads were two
 * different places.
 */
class OrderNotifyResendTest extends TestCase
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

        return Order::latest('id')->first();
    }

    public function test_the_shops_own_emails_are_in_the_panels_catalogue(): void
    {
        $sendable = app(OrderNotificationManifest::class)->sendable();

        $this->assertArrayHasKey('order-confirmation', $sendable);
        $this->assertArrayHasKey('order-paid', $sendable);

        // A label, not the raw key echoed back — that is what an untranslated
        // registration looks like.
        $this->assertNotSame('order-confirmation', $sendable['order-confirmation']);
    }

    public function test_resending_a_confirmation_sends_the_shops_mailable(): void
    {
        $this->seedBaseData();
        $order = $this->placeOrder();

        Notification::fake();

        app(NotifiesCustomer::class)->execute($order, 'order-confirmation');

        Notification::assertSentTimes(OrderConfirmationNotification::class, 1);
    }

    /**
     * An order placed through the API may carry no contact email at all —
     * CheckoutController marks it `nullable`, unlike the web request. Lunar's
     * own recipient rule reads only the two addresses and would refuse to send
     * on such an order, even though every automatic email reaches it via the
     * linked user.
     */
    public function test_a_missing_contact_email_falls_back_to_the_linked_user(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $this->actingAs($user);
        $order = $this->placeOrder();

        $order->loadMissing(['shippingAddress', 'billingAddress']);
        $order->shippingAddress?->update(['contact_email' => null]);
        $order->billingAddress?->update(['contact_email' => null]);
        $order->user_id = $user->id;
        $order->save();

        Notification::fake();

        app(NotifiesCustomer::class)->execute($order->fresh(), 'order-confirmation');

        Notification::assertSentTimes(OrderConfirmationNotification::class, 1);
    }
}
