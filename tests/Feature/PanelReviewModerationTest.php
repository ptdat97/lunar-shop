<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Catalog\Models\Review;
use Modules\Catalog\Panel\ReviewResource;
use Modules\Catalog\Services\ReviewService;
use Modules\Core\Support\Settings;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The review moderation queue.
 *
 * Reviews never had an admin screen — a gap that predates the 2.0 upgrade
 * rather than a casualty of it. With `review.auto_approve` off every review
 * lands unapproved, and there was no way at all to publish one; the demo data
 * alone left 40 sitting in a queue nothing could drain.
 */
class PanelReviewModerationTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
    }

    private function review(bool $approved = false): Review
    {
        return Review::create([
            'product_id' => $this->createProduct()->id,
            'author' => 'Mai',
            'rating' => 5,
            'body' => 'Vải đẹp, đúng size.',
            'approved' => $approved,
        ]);
    }

    /** A queue sorted by date buries what needs a decision. */
    public function test_pending_reviews_come_first(): void
    {
        $this->review(approved: true);
        $pending = $this->review(approved: false);

        $this->get(route('panel.shop.reviews.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('rows.0.id', $pending->id)
                ->has('rows.0._actions.approve')
                ->missing('rows.0._actions.unapprove'),
            );
    }

    public function test_approving_publishes_the_review(): void
    {
        $review = $this->review();

        $this->post(route('panel.shop.reviews.action', [$review->id, 'approve']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($review->fresh()->approved);
    }

    /**
     * The reason moderation lives in ReviewService: a product's rating counts
     * approved rows only, and the summary memo has to drop in the same request
     * or a staff member who approves then looks at the product sees the old
     * average.
     */
    public function test_approving_updates_the_products_rating_summary(): void
    {
        $review = $this->review();
        $service = app(ReviewService::class);

        $this->assertSame(0, $service->summaryFor($review->product_id)['count']);

        $service->approve($review->fresh());

        $this->assertSame(1, $service->summaryFor($review->product_id)['count']);
        $this->assertSame(5.0, $service->summaryFor($review->product_id)['average']);
    }

    /** Unpublishing takes it off the storefront without destroying the words. */
    public function test_unapproving_hides_it_but_keeps_it(): void
    {
        $review = $this->review(approved: true);

        $this->post(route('panel.shop.reviews.action', [$review->id, 'unapprove']))
            ->assertRedirect();

        $this->assertFalse($review->fresh()->approved);
        $this->assertSame('Vải đẹp, đúng size.', $review->fresh()->body);
    }

    /** An action the row does not offer must be refused server-side too. */
    public function test_approving_an_already_approved_review_is_refused(): void
    {
        $review = $this->review(approved: true);

        $this->post(route('panel.shop.reviews.action', [$review->id, 'approve']))
            ->assertNotFound();
    }

    /** Staff moderate; they do not rewrite what a customer wrote. */
    public function test_the_review_body_cannot_be_edited_from_the_panel(): void
    {
        $review = $this->review();

        $this->get(route('panel.shop.reviews.create'))->assertNotFound();

        $this->put(route('panel.shop.reviews.update', $review->id), ['body' => 'Sửa trộm'])
            ->assertNotFound();

        $this->assertSame('Vải đẹp, đúng size.', $review->fresh()->body);
    }

    /** Spam removal stays available. */
    public function test_a_review_can_be_deleted(): void
    {
        $review = $this->review();

        $this->delete(route('panel.shop.reviews.destroy', $review->id))
            ->assertRedirect(route('panel.shop.reviews.index'));

        $this->assertNull($review->fresh());
    }

    /**
     * The whole reason the screen matters: with auto-approve off, a review
     * reaching the storefront depends on somebody being able to approve it.
     */
    public function test_with_auto_approve_off_a_review_only_appears_once_approved(): void
    {
        app(Settings::class)->put('review', ['auto_approve' => false]);

        $product = $this->createProduct();
        $service = app(ReviewService::class);

        $review = $service->add($product->id, 'Lan', 4, 'Ổn.');

        $this->assertFalse($review->approved);
        $this->assertSame(0, $service->forProduct($product->id)->total());

        $this->post(route('panel.shop.reviews.action', [$review->id, 'approve']))
            ->assertRedirect();

        $this->assertSame(1, app(ReviewService::class)->forProduct($product->id)->total());
    }

    /**
     * A queue nobody looks at is a queue that does not work, and nobody opens a
     * screen on the off-chance — so the count reaches the navigation as a badge.
     *
     * Asserted on the resource rather than on a rendered navigation array: the
     * panel builds its NavigationItems once, while processing sections at boot,
     * and bakes the badge into a readonly property. Under PHP-FPM that is once
     * per request, so the number a staff member sees is current. Were this app
     * ever moved to Octane the item would outlive the request and the badge
     * would go stale — the thing to change then is `NavigationItem`, not this.
     */
    public function test_the_navigation_badge_counts_what_is_waiting(): void
    {
        $this->review(approved: false);
        $this->review(approved: false);
        $this->review(approved: true);

        $this->assertSame('2', app(ReviewResource::class)->navigationBadge());
    }

    /** An idle shop carries no nagging badge. */
    public function test_there_is_no_badge_when_nothing_is_waiting(): void
    {
        $this->review(approved: true);

        $this->assertNull(app(ReviewResource::class)->navigationBadge());
    }

    /** And the panel does carry a badge slot for it to land in. */
    public function test_the_reviews_navigation_entry_exposes_a_badge_slot(): void
    {
        $nav = $this->get(route('panel.dashboard'))
            ->assertOk()
            ->viewData('page')['props']['navigation'] ?? [];

        $find = function (array $nodes) use (&$find): ?array {
            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                if (($node['key'] ?? null) === 'reviews') {
                    return $node;
                }

                if ($hit = $find($node)) {
                    return $hit;
                }
            }

            return null;
        };

        $entry = $find($nav);

        $this->assertNotNull($entry, 'Mục Đánh giá không có trong điều hướng.');
        $this->assertArrayHasKey('badge', $entry);
    }
}
