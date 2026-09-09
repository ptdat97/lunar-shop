<?php

namespace Modules\Order\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Core\Panel\RowAction;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Services\ReturnService;

/**
 * The returns queue.
 *
 * Not a CRUD screen: a return is opened by a customer and worked by staff, so
 * nothing here creates or deletes one. What staff do is move it through its
 * states — approve (optionally refunding at the same time), refund, reject —
 * and each of those is ReturnService's job, not this class's.
 *
 * Which buttons a row shows comes from that row's own status, so a browser tab
 * left open on yesterday's queue cannot refund a return twice: the action
 * re-checks availability server-side before running.
 */
class ReturnRequestResource extends PanelResource
{
    public function model(): string
    {
        return ReturnRequest::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function key(): string
    {
        return 'returns';
    }

    public function label(): string
    {
        return __('admin.returns.plural');
    }

    public function singular(): string
    {
        return __('admin.returns.label');
    }

    public function icon(): string
    {
        return 'undo';
    }

    public function permission(): string
    {
        return 'sales:manage-orders';
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(): bool
    {
        return false;
    }

    /**
     * The form is a read-only record sheet: everything on a return is either
     * the customer's words or the outcome of a ReturnService call, and letting
     * staff retype a refund amount would only put the ledger out of step with
     * the gateway.
     */
    public function canEdit(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::text('reference', __('admin.returns.reference'))->onIndex()->width(6),
            Field::select('status', __('admin.returns.status'), self::statuses())->onIndex()->width(6),
            Field::textarea('reason', __('admin.returns.reason'))->onIndex(),
            Field::textarea('staff_note', __('admin.returns.staff_note')),
        ];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            ReturnRequest::REQUESTED => __('admin.returns.status_requested'),
            ReturnRequest::APPROVED => __('admin.returns.status_approved'),
            ReturnRequest::REJECTED => __('admin.returns.status_rejected'),
            ReturnRequest::REFUNDED => __('admin.returns.status_refunded'),
            ReturnRequest::COMPLETED => __('admin.returns.status_refunded'),
        ];
    }

    public function rowActions(): array
    {
        return [
            RowAction::make('approve', __('admin.returns.approve'))
                ->icon('check')
                ->primary()
                ->when(fn (ReturnRequest $return) => $return->status === ReturnRequest::REQUESTED)
                ->accepts([
                    'refund' => ['nullable', 'boolean'],
                    'staff_note' => ['nullable', 'string', 'max:2000'],
                ])
                ->run(fn (ReturnRequest $return, Request $request) => app(ReturnService::class)->approve(
                    $return,
                    $request->boolean('refund'),
                    $request->input('staff_note'),
                )),

            RowAction::make('refund', __('admin.returns.refund'))
                ->icon('percent')
                ->confirm(__('admin.returns.refund'))
                ->when(fn (ReturnRequest $return) => $return->status === ReturnRequest::APPROVED)
                ->run(fn (ReturnRequest $return) => app(ReturnService::class)->refund($return)),

            RowAction::make('reject', __('admin.returns.reject'))
                ->icon('close')
                ->confirm(__('admin.returns.reject'))
                ->when(fn (ReturnRequest $return) => $return->status === ReturnRequest::REQUESTED)
                ->accepts(['staff_note' => ['nullable', 'string', 'max:2000']])
                ->run(fn (ReturnRequest $return, Request $request) => app(ReturnService::class)->reject(
                    $return,
                    $request->input('staff_note'),
                )),
        ];
    }

    public function indexQuery(Builder $query): Builder
    {
        return $query->with('order')->withCount('lines');
    }

    public function computed(): array
    {
        return [
            'order_reference' => fn (ReturnRequest $return) => $return->order?->reference,
            'lines_count' => fn (ReturnRequest $return) => $return->lines_count,
        ];
    }

    public function computedLabels(): array
    {
        return [
            'order_reference' => __('admin.returns.order'),
            'lines_count' => __('admin.returns.items'),
        ];
    }

    public function searchable(): array
    {
        return ['reference', 'reason'];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }
}
