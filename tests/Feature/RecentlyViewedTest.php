<?php

namespace Tests\Feature;

use Modules\Customer\Models\RecentlyViewedProduct;
use Modules\Customer\Services\RecentlyViewedService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Server-side "recently viewed".
 *
 * The storefront's list lives in `localStorage`, which the mobile app cannot
 * read — a signed-in shopper saw a different list on each device. Guests keep
 * the browser list; only identified shoppers get a stored one.
 */
class RecentlyViewedTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/customer/recently-viewed')->assertStatus(401);
        $this->postJson('/api/v1/customer/recently-viewed', ['product_id' => 1])->assertStatus(401);
    }

    public function test_a_view_is_recorded_and_returned(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $product = $this->createProduct(['name' => 'Wool Coat']);

        $this->actingAs($user)
            ->postJson('/api/v1/customer/recently-viewed', ['product_id' => $product->id])
            ->assertStatus(201);

        $this->actingAs($user)
            ->getJson('/api/v1/customer/recently-viewed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wool Coat');
    }

    public function test_the_list_is_newest_first_and_a_re_view_moves_to_the_top(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $first = $this->createProduct(['name' => 'First']);
        $second = $this->createProduct(['name' => 'Second']);

        $service = app(RecentlyViewedService::class);
        $service->record($user, $first->id);
        $service->record($user, $second->id);

        $this->assertSame([$second->id, $first->id], $service->productIdsFor($user));

        // Looking at it again promotes it rather than adding a duplicate row.
        $service->record($user, $first->id);

        $this->assertSame([$first->id, $second->id], $service->productIdsFor($user));
        $this->assertSame(2, RecentlyViewedProduct::where('user_id', $user->id)->count());
    }

    public function test_views_in_the_same_millisecond_still_order_correctly(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $service = app(RecentlyViewedService::class);

        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $id = $this->createProduct(['name' => "P{$i}"])->id;
        }

        // Back-to-back calls share a millisecond (measured: ~100% of the time),
        // so ordering by `viewed_at` was a coin flip. `sequence` is monotonic.
        foreach ($ids as $id) {
            $service->record($user, $id);
        }

        $this->assertSame(array_reverse($ids), $service->productIdsFor($user));
    }

    public function test_the_stored_list_is_capped(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $service = app(RecentlyViewedService::class);

        // A shopper who browses a lot must not grow the table without bound.
        foreach (range(1, RecentlyViewedService::MAX + 5) as $i) {
            $service->record($user, $this->createProduct(['name' => "P{$i}"])->id);
        }

        $this->assertSame(
            RecentlyViewedService::MAX,
            RecentlyViewedProduct::where('user_id', $user->id)->count(),
        );
    }

    public function test_an_unpublished_product_is_not_recorded(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $product = $this->createProduct();
        $product->update(['status' => 'draft']);

        $this->actingAs($user)
            ->postJson('/api/v1/customer/recently-viewed', ['product_id' => $product->id])
            ->assertStatus(404);

        $this->assertSame(0, RecentlyViewedProduct::count());
    }

    public function test_an_unknown_product_is_rejected(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/api/v1/customer/recently-viewed', ['product_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('product_id');
    }

    public function test_a_product_unpublished_after_viewing_drops_out_of_the_list(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $product = $this->createProduct();

        app(RecentlyViewedService::class)->record($user, $product->id);
        $product->update(['status' => 'draft']);

        $this->actingAs($user)
            ->getJson('/api/v1/customer/recently-viewed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_lists_are_isolated_per_user(): void
    {
        $this->seedBaseData();
        $mine = $this->createUser();
        $theirs = $this->createUser();
        $product = $this->createProduct();

        app(RecentlyViewedService::class)->record($theirs, $product->id);

        $this->actingAs($mine)
            ->getJson('/api/v1/customer/recently-viewed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_list_can_be_cleared(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        app(RecentlyViewedService::class)->record($user, $this->createProduct()->id);

        $this->actingAs($user)
            ->deleteJson('/api/v1/customer/recently-viewed')
            ->assertOk()
            ->assertJsonPath('data.cleared', 1);

        $this->assertSame(0, RecentlyViewedProduct::where('user_id', $user->id)->count());
    }
}
