<?php

namespace Tests\Feature;

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Modules\Notification\Models\DeviceToken;
use Modules\Notification\Notifications\OrderStatusChanged;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The in-app inbox and push registry a mobile client talks to.
 */
class NotificationApiTest extends TestCase
{
    use CreatesStorefrontData;

    private function notify($user, string $reference = 'REF-1'): void
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'user_id' => $user->id,
            'status' => 'dispatched',
            'reference' => $reference,
            'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => 1000,
        ]);

        $user->notify(new OrderStatusChanged($order, 'payment-received'));
    }

    public function test_the_inbox_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_the_inbox_lists_notifications_newest_first(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $this->notify($user, 'REF-1');
        $this->notify($user, 'REF-2');

        $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();

        $this->assertSame(2, $response->json('unread'));
        $this->assertSame('order.status_changed', $response->json('data.0.type'));
        $this->assertFalse($response->json('data.0.read'));

        // `meta` keeps exactly the API-wide pagination keys.
        $this->assertSame(
            ['page', 'per_page', 'last_page', 'total'],
            array_keys($response->json('meta')),
        );
    }

    public function test_the_payload_hides_the_php_class_and_exposes_a_semantic_type(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $this->notify($user);

        $item = $this->actingAs($user)->getJson('/api/v1/notifications')->json('data.0');

        // A client must not couple to a namespace it cannot see.
        $this->assertSame('order.status_changed', $item['type']);
        $this->assertArrayHasKey('order_id', $item['data']);
        $this->assertArrayNotHasKey('title', $item['data'], 'title is promoted, not duplicated');
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $this->notify($user);

        $id = $this->actingAs($user)->getJson('/api/v1/notifications')->json('data.0.id');

        $this->actingAs($user)->postJson("/api/v1/notifications/{$id}/read")->assertOk();

        $this->assertSame(0, $this->actingAs($user)->getJson('/api/v1/notifications')->json('unread'));
    }

    public function test_another_users_notification_cannot_be_marked_read(): void
    {
        $this->seedBaseData();
        $owner = $this->createUser();
        $this->notify($owner);
        $id = $owner->notifications()->first()->id;

        $other = $this->createUser();

        $this->actingAs($other)->postJson("/api/v1/notifications/{$id}/read")->assertStatus(404);
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    public function test_read_all_clears_the_unread_count(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $this->notify($user, 'REF-1');
        $this->notify($user, 'REF-2');

        $this->actingAs($user)->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marked_read', 2);

        $this->assertSame(0, $this->actingAs($user)->getJson('/api/v1/notifications')->json('unread'));
    }

    public function test_a_device_registers_and_re_registration_is_idempotent(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $payload = ['token' => 'fcm-abc', 'platform' => 'android', 'device_name' => 'Pixel 8'];

        $this->actingAs($user)->postJson('/api/v1/devices', $payload)->assertStatus(201);

        // The app re-registers on every launch.
        $this->actingAs($user)->postJson('/api/v1/devices', $payload)->assertStatus(200);

        $this->assertSame(1, DeviceToken::where('user_id', $user->id)->count());
    }

    public function test_a_device_token_moves_to_whoever_registers_it_last(): void
    {
        $this->seedBaseData();
        $first = $this->createUser();
        $second = $this->createUser();

        $payload = ['token' => 'shared-handset', 'platform' => 'ios'];

        $this->actingAs($first)->postJson('/api/v1/devices', $payload)->assertSuccessful();
        $this->actingAs($second)->postJson('/api/v1/devices', $payload)->assertSuccessful();

        // Otherwise the previous owner keeps receiving this handset's pushes.
        $this->assertSame(0, DeviceToken::where('user_id', $first->id)->count());
        $this->assertSame(1, DeviceToken::where('user_id', $second->id)->count());
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/api/v1/devices', ['token' => 't', 'platform' => 'blackberry'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('platform');
    }

    public function test_a_device_is_unregistered_on_sign_out(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        DeviceToken::create(['user_id' => $user->id, 'token' => 'fcm-abc', 'platform' => 'ios']);

        $this->actingAs($user)
            ->deleteJson('/api/v1/devices', ['token' => 'fcm-abc'])
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_another_users_device_cannot_be_unregistered(): void
    {
        $this->seedBaseData();
        $owner = $this->createUser();
        $other = $this->createUser();
        DeviceToken::create(['user_id' => $owner->id, 'token' => 'fcm-abc', 'platform' => 'ios']);

        $this->actingAs($other)
            ->deleteJson('/api/v1/devices', ['token' => 'fcm-abc'])
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertSame(1, DeviceToken::count());
    }
}
