<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Content\Models\Lookbook;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The lookbook screen — where the resource engine writes real child tables
 * instead of a JSON column.
 *
 * The thing worth guarding is row identity: a lookbook item is pinned to a
 * photo by that photo's id, so a save that deleted and recreated the photos
 * would silently unpin everything while reporting success.
 */
class PanelLookbookTest extends TestCase
{
    use CreatesStorefrontData;

    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    private function lookbook(): Lookbook
    {
        return Lookbook::create(['title' => 'Thu Đông', 'slug' => 'thu-dong', 'published' => true]);
    }

    public function test_child_rows_are_created_with_order_from_their_position(): void
    {
        $lookbook = $this->lookbook();

        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [
                    ['image' => '/1.jpg', 'caption' => 'Một'],
                    ['image' => '/2.jpg', 'caption' => 'Hai'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $images = $lookbook->fresh()->images;

        $this->assertCount(2, $images);
        // `sort` is written from the row's position — the admin reorders with
        // the repeater's arrows, never by typing numbers.
        $this->assertSame([0, 1], $images->pluck('sort')->all());
        $this->assertSame(['Một', 'Hai'], $images->pluck('caption')->all());
    }

    /**
     * The reason hasMany rows round-trip their id: a lookbook item points at a
     * photo, and losing that photo's id unpins it.
     */
    public function test_editing_keeps_child_ids_so_pins_survive(): void
    {
        $lookbook = $this->lookbook();
        $product = $this->createProduct();

        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [['image' => '/1.jpg', 'caption' => 'Ảnh nền']],
            ])
            ->assertSessionHasNoErrors();

        $imageId = $lookbook->fresh()->images->first()->id;

        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [['id' => $imageId, 'image' => '/1.jpg', 'caption' => 'Ảnh nền']],
                'items' => [[
                    'product_id' => $product->id,
                    'caption' => 'Áo khoác',
                    'image_id' => $imageId,
                    'pos_x' => 40,
                    'pos_y' => 60,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $fresh = $lookbook->fresh();

        $this->assertSame($imageId, $fresh->images->first()->id, 'Ảnh bị tạo lại, pin sẽ đứt.');
        $this->assertSame($imageId, $fresh->items->first()->image_id);
        $this->assertTrue($fresh->items->first()->isHotspot());

        // A third save with the same ids must still not churn them.
        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [['id' => $imageId, 'image' => '/1.jpg', 'caption' => 'Đổi chú thích']],
                'items' => $fresh->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'caption' => $item->caption,
                    'image_id' => $item->image_id,
                    'pos_x' => $item->pos_x,
                    'pos_y' => $item->pos_y,
                ])->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($imageId, $lookbook->fresh()->images->first()->id);
        $this->assertSame('Đổi chú thích', $lookbook->fresh()->images->first()->caption);
    }

    public function test_dropping_a_row_deletes_the_child(): void
    {
        $lookbook = $this->lookbook();

        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [
                    ['image' => '/1.jpg', 'caption' => 'Một'],
                    ['image' => '/2.jpg', 'caption' => 'Hai'],
                ],
            ])->assertSessionHasNoErrors();

        $keep = $lookbook->fresh()->images->first();

        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [['id' => $keep->id, 'image' => '/1.jpg', 'caption' => 'Một']],
            ])->assertSessionHasNoErrors();

        $this->assertCount(1, $lookbook->fresh()->images);

        // Clearing the list entirely must empty the relation, not leave it be.
        $this->actingAsAdmin()
            ->put(route('panel.shop.lookbooks.update', $lookbook->id), [
                'title' => 'Thu Đông',
                'slug' => 'thu-dong',
                'published' => true,
                'images' => [],
            ])->assertSessionHasNoErrors();

        $this->assertCount(0, $lookbook->fresh()->images);
    }

    /** The pin picker must offer this lookbook's photos, not every photo. */
    public function test_pin_picker_is_scoped_to_this_lookbooks_photos(): void
    {
        $mine = $this->lookbook();
        $other = Lookbook::create(['title' => 'Xuân Hè', 'slug' => 'xuan-he']);

        $myImage = $mine->images()->create(['image' => '/mine.jpg', 'caption' => 'Của tôi', 'sort' => 0]);
        $otherImage = $other->images()->create(['image' => '/other.jpg', 'caption' => 'Của người', 'sort' => 0]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.lookbooks.edit', $mine->id))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($myImage, $otherImage) {
                $picker = collect($page->toArray()['props']['fields'])
                    ->flatMap(fn (array $field) => $field['children'] ?? [])
                    ->firstWhere('name', 'image_id');

                $this->assertArrayHasKey((string) $myImage->id, $picker['options']);
                $this->assertArrayNotHasKey((string) $otherImage->id, $picker['options']);
            });
    }

    /** The index counts children without an N+1 per row. */
    public function test_index_shows_child_counts(): void
    {
        $lookbook = $this->lookbook();
        $lookbook->images()->create(['image' => '/1.jpg', 'sort' => 0]);
        $lookbook->images()->create(['image' => '/2.jpg', 'sort' => 1]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.lookbooks.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.images_count', 2)
                ->where('rows.0.items_count', 0),
            );
    }
}
