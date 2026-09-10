<?php

namespace Modules\Catalog\Panel;

use Illuminate\Database\Eloquent\Builder;
use Modules\Catalog\Models\Review;
use Modules\Catalog\Services\ReviewService;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Core\Panel\RowAction;

/**
 * The review moderation queue.
 *
 * Reviews have never had an admin screen — not a casualty of the 2.0 upgrade,
 * a gap that predates it. It matters because moderation is a real workflow:
 * with `review.auto_approve` off, every review lands unapproved and there was
 * no way at all to publish one. The demo data alone had 40 sitting in a queue
 * nothing could drain.
 *
 * Staff decide whether a review appears; they do not rewrite what a customer
 * wrote, so this offers no create and no edit form — only approve, unapprove
 * and delete.
 */
class ReviewResource extends PanelResource
{
    public function model(): string
    {
        return Review::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Đánh giá gắn với sản phẩm. Mục của Lunar trong nhóm này đều ở 50, nên 60 đưa nó xuống ngay sau. */
    public function navigationGroup(): string
    {
        return 'catalog';
    }

    public function navigationPriority(): int
    {
        return 60;
    }

    public function key(): string
    {
        return 'reviews';
    }

    public function label(): string
    {
        return __('admin.review.plural');
    }

    public function singular(): string
    {
        return __('admin.review.label');
    }

    public function icon(): string
    {
        return 'check';
    }

    public function permission(): string
    {
        return 'catalog:manage-products';
    }

    /**
     * How many reviews are waiting on a decision.
     *
     * Moderation only works if somebody knows there is something to moderate,
     * and nobody opens a screen on the off-chance. Null when the queue is
     * empty, so an idle shop carries no nagging badge.
     */
    public function navigationBadge(): ?string
    {
        $pending = app(ReviewService::class)->pendingCount();

        return $pending > 0 ? (string) $pending : null;
    }

    public function canCreate(): bool
    {
        return false;
    }

    /** Staff moderate a review; they do not rewrite the customer's words. */
    public function canEdit(): bool
    {
        return false;
    }

    /** Spam is real, so removal stays. */
    public function canDelete(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            Field::text('author', __('admin.review.author'))->onIndex()->width(4),
            Field::number('rating', __('admin.review.rating'))->onIndex()->width(2),
            Field::toggle('approved', __('admin.review.approved'))->onIndex()->width(2),
            Field::textarea('body', __('admin.review.body')),
        ];
    }

    public function rowActions(): array
    {
        return [
            RowAction::make('approve', __('admin.review.approve'))
                ->icon('check')
                ->primary()
                ->when(fn (Review $review) => ! $review->approved)
                ->run(fn (Review $review) => app(ReviewService::class)->approve($review)),

            RowAction::make('unapprove', __('admin.review.unapprove'))
                ->icon('eyeOff')
                ->confirm(__('admin.review.unapprove'))
                ->when(fn (Review $review) => (bool) $review->approved)
                ->run(fn (Review $review) => app(ReviewService::class)->unapprove($review)),
        ];
    }

    /**
     * Eager-loads the product so the column below is not a query per row, and
     * puts what needs a decision at the top — a moderation queue sorted by date
     * buries the pending ones under everything already handled.
     */
    public function indexQuery(Builder $query): Builder
    {
        return $query->with('product')->orderBy('approved');
    }

    public function computed(): array
    {
        return [
            'product' => fn (Review $review) => $review->product?->translate('name'),
            'submitted_at' => fn (Review $review) => $review->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function computedLabels(): array
    {
        return [
            'product' => __('admin.review.product'),
            'submitted_at' => __('admin.review.submitted_at'),
        ];
    }

    public function searchable(): array
    {
        return ['author', 'body'];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }
}
