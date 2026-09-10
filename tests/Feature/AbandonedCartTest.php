<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Lunar\Core\Models\Cart;
use Modules\Checkout\Mail\AbandonedCartMail;
use Modules\Checkout\Services\AbandonedCartService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * One reminder per abandoned cart, and never to the wrong person.
 *
 * The risk here is not that the feature fails to send — it is that it sends too
 * much, or sends about a cart the shopper has already paid for. Both look fine
 * in a passing "it sent an email" test and are only visible to the customer.
 */
class AbandonedCartTest extends TestCase
{
    use CreatesStorefrontData;

    /** A cart with lines and a contact email, last touched `$minutesAgo` ago. */
    private function abandonedCart(int $minutesAgo = 120): Cart
    {
        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])
            ->assertSuccessful();

        $cart = Cart::latest('id')->firstOrFail();

        // `saveQuietly` chỉ bỏ event, KHÔNG bỏ timestamp — nó vẫn ghi
        // `updated_at = now()` và xoá đúng cái ta vừa đặt. Phải withoutTimestamps.
        Cart::withoutTimestamps(
            fn () => $cart->forceFill(['updated_at' => now()->subMinutes($minutesAgo)])->saveQuietly(),
        );

        return $cart->fresh();
    }

    private function enable(): void
    {
        config([
            'checkout.abandoned_cart_enabled' => true,
            'checkout.abandoned_cart_minutes' => 60,
        ]);
    }

    public function test_a_stale_cart_with_an_email_gets_one_reminder(): void
    {
        $this->seedBaseData();
        $this->enable();
        $cart = $this->abandonedCart();

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        // ShouldQueue → Mail::fake() ghi nhận là QUEUED, không phải sent.
        Mail::assertQueued(AbandonedCartMail::class, 1);
        $this->assertNotNull($cart->fresh()->abandoned_reminded_at);
    }

    /** The line between a reminder and spam is the second email. */
    public function test_a_second_sweep_does_not_remind_again(): void
    {
        $this->seedBaseData();
        $this->enable();
        $this->abandonedCart();

        Artisan::call('carts:remind-abandoned');

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingQueued();
    }

    public function test_a_cart_still_being_shopped_is_left_alone(): void
    {
        $this->seedBaseData();
        $this->enable();
        $this->abandonedCart(minutesAgo: 5);

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingQueued();
    }

    /** Emailing about a cart the shopper already paid for reads as broken. */
    public function test_a_completed_cart_is_never_reminded(): void
    {
        $this->seedBaseData();
        $this->enable();
        $cart = $this->abandonedCart();

        Cart::withoutTimestamps(fn () => $cart->forceFill(['completed_at' => now()])->saveQuietly());

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingQueued();
    }

    /**
     * A guest who left before the address step has given the shop no way to
     * reach them. Guessing is not an option.
     */
    public function test_a_cart_with_no_contact_email_is_skipped(): void
    {
        $this->seedBaseData();
        $this->enable();

        $product = $this->createProduct(['stock' => 5]);
        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $bare = Cart::latest('id')->firstOrFail();
        Cart::withoutTimestamps(
            fn () => $bare->forceFill(['updated_at' => now()->subHours(3)])->saveQuietly(),
        );

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingQueued();
    }

    /** Ancient carts are past the point where a reminder is welcome. */
    public function test_a_cart_older_than_the_horizon_is_skipped(): void
    {
        $this->seedBaseData();
        $this->enable();
        $this->abandonedCart(minutesAgo: (AbandonedCartService::STALE_AFTER_DAYS + 1) * 24 * 60);

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingQueued();
    }

    /** Off by default: the first sweep after deploy must not mail everyone. */
    public function test_nothing_is_sent_while_the_feature_is_off(): void
    {
        $this->seedBaseData();
        config(['checkout.abandoned_cart_enabled' => false]);
        $this->abandonedCart();

        Mail::fake();
        Artisan::call('carts:remind-abandoned');

        Mail::assertNothingQueued();
    }

    /** The dry run is how a shop looks before switching it on — it must work while off. */
    public function test_the_dry_run_reports_without_sending_or_marking(): void
    {
        $this->seedBaseData();
        config(['checkout.abandoned_cart_enabled' => false]);
        $cart = $this->abandonedCart();

        Mail::fake();
        Artisan::call('carts:remind-abandoned', ['--dry-run' => true]);

        Mail::assertNothingQueued();
        $this->assertNull($cart->fresh()->abandoned_reminded_at, 'Chạy thử mà vẫn đánh dấu đã nhắc.');
        $this->assertStringContainsString('[thử]', Artisan::output());
    }

    public function test_the_recovery_link_restores_the_cart(): void
    {
        $this->seedBaseData();
        $cart = $this->abandonedCart();
        app(AbandonedCartService::class)->ensureRecoveryToken($cart);
        $cart = $cart->fresh();

        $this->get(route('storefront.cart.recover', ['token' => $cart->public_token]))
            ->assertRedirect(route('storefront.cart'));
    }

    /** A link to a cart that has since been paid for must not resurrect it. */
    public function test_the_recovery_link_dies_with_the_cart(): void
    {
        $this->seedBaseData();
        $cart = $this->abandonedCart();
        app(AbandonedCartService::class)->ensureRecoveryToken($cart);
        Cart::withoutTimestamps(fn () => $cart->forceFill(['completed_at' => now()])->saveQuietly());
        $cart = $cart->fresh();

        $this->get(route('storefront.cart.recover', ['token' => $cart->public_token]))
            ->assertRedirect(route('storefront.cart'))
            ->assertSessionHas('status');
    }

    /** Keyed on the opaque token, never the id. */
    public function test_an_unknown_token_is_not_an_error_page(): void
    {
        $this->seedBaseData();

        $this->get(route('storefront.cart.recover', ['token' => 'khong-ton-tai']))
            ->assertRedirect(route('storefront.cart'));
    }
}
