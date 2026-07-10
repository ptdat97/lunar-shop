<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Modules\Customer\Services\CustomerResolver;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * An order's status history, served from Lunar's activity log.
 *
 * No `order_timeline` table exists: `Lunar\Models\Order` already uses
 * LogsActivity, and Lunar's own observer writes a `status-update` entry with
 * `{previous, new}`. Duplicating that in our own table would fork the commerce
 * core's own record of what happened.
 */
class OrderTimelineTest extends TestCase
{
    use CreatesStorefrontData;

    private function orderFor($user): Order
    {
        $customer = app(CustomerResolver::class)->forUser($user);

        return Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'awaiting-payment',
            'reference' => 'TL-1',
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ]);
    }

    public function test_the_timeline_lists_transitions_oldest_first(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->orderFor($user);

        $order->update(['status' => 'payment-received']);
        $order->update(['status' => 'dispatched']);

        $timeline = $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/timeline")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $timeline);
        $this->assertSame('payment-received', $timeline[0]['status']);
        $this->assertSame('awaiting-payment', $timeline[0]['previous_status']);
        $this->assertSame('dispatched', $timeline[1]['status']);
    }

    public function test_the_timeline_never_leaks_internal_column_diffs(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->orderFor($user);

        // A plain attribute change logs a generic `updated` row whose properties
        // are a full column diff — that must never reach the customer.
        $order->update(['notes' => 'internal packing note']);
        $order->update(['status' => 'dispatched']);

        $response = $this->actingAs($user)->getJson("/api/v1/orders/{$order->id}/timeline")->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertStringNotContainsString('internal packing note', $response->getContent());
        $this->assertStringNotContainsString('notes', $response->getContent());
    }

    public function test_each_entry_carries_a_localised_label(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->orderFor($user);
        $order->update(['status' => 'dispatched']);

        $entry = $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/timeline?locale=vi")
            ->assertOk()
            ->json('data.0');

        $this->assertSame('dispatched', $entry['status']);
        $this->assertSame('Đang giao', $entry['status_label']);
    }

    public function test_an_order_with_no_logged_transition_still_has_a_timeline(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $order = $this->orderFor($user);

        // Orders placed before the log had anything to say.
        DB::table('activity_log')->where('subject_id', $order->id)->delete();

        $timeline = $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/timeline")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $timeline);
        $this->assertSame('awaiting-payment', $timeline[0]['status']);
    }

    public function test_another_customers_timeline_is_not_readable(): void
    {
        $this->seedBaseData();
        $owner = $this->createUser();
        $order = $this->orderFor($owner);
        $order->update(['status' => 'dispatched']);

        $other = $this->createUser();
        app(CustomerResolver::class)->forUser($other);

        $this->actingAs($other)
            ->getJson("/api/v1/orders/{$order->id}/timeline")
            ->assertStatus(404);
    }

    public function test_the_timeline_requires_authentication(): void
    {
        $this->getJson('/api/v1/orders/1/timeline')->assertStatus(401);
    }
}
