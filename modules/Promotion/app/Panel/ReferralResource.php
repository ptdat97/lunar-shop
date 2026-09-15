<?php

namespace Modules\Promotion\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Models\Customer;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Core\Panel\RowAction;
use Modules\Promotion\Models\ReferralClaim;
use Modules\Promotion\Services\ReferralService;

/**
 * Hàng đợi giới thiệu bạn: ai mời ai, đang ở bước nào, và đóng lượt gian lận.
 *
 * Chỉ đọc, cộng hai hành động theo dòng — mọi dòng ở đây do khách tạo ra, nên
 * một nút "Thêm lượt giới thiệu" chỉ sản xuất dữ liệu rác, và sửa một dòng
 * nghĩa là sửa lịch sử ai mời ai.
 *
 * Hai hành động là hai việc vận hành có thật:
 *
 *   release  đơn đã qua hạn đổi/trả nhưng lượt còn nằm chờ (lượt quét chạy
 *            hằng ngày; đây là cách không phải chờ tới lượt kế tiếp)
 *   void     lượt gian lận, hoặc một thỏa thuận khác mà hệ thống không biết
 *
 * Xoá thì không: một lượt giới thiệu là chứng từ của việc đã phát coupon, và
 * xoá nó đi chỉ làm mất dấu vết chứ không thu lại được gì.
 */
class ReferralResource extends PanelResource
{
    public function model(): string
    {
        return ReferralClaim::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Tiếp khách: đặt cùng nhóm với Đổi/trả và Khách hàng. */
    public function navigationGroup(): string
    {
        return 'sales';
    }

    public function navigationPriority(): int
    {
        return 25;
    }

    public function key(): string
    {
        return 'referrals';
    }

    public function label(): string
    {
        return __('admin.referral.plural');
    }

    public function singular(): string
    {
        return __('admin.referral.label');
    }

    public function icon(): string
    {
        return 'users';
    }

    /** Cùng quyền với Đổi/trả: đây là việc chăm sóc khách, không phải cấu hình. */
    public function permission(): string
    {
        return 'sales:manage-orders';
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(): bool
    {
        return false;
    }

    public function canDelete(): bool
    {
        return false;
    }

    /**
     * Số lượt còn phải xử lý.
     *
     * Đếm cả `claimed` (chờ đơn đầu tiên) lẫn `awaiting` (chờ hết hạn đổi/trả):
     * con số này cho staff biết có gì đang treo, chứ không phải có gì cần bấm.
     *
     * `Schema::hasTable` không phải phòng xa: badge được resolve mỗi lần app
     * boot — kể cả lúc chạy `artisan migrate`, tức là TRƯỚC khi bảng này được
     * tạo. Thiếu guard thì một lần cài mới sẽ chết ngay ở lệnh migrate dựng ra
     * bảng. Bảng không có = hàng đợi rỗng.
     */
    public function navigationBadge(): ?string
    {
        if (! Schema::hasTable((new ReferralClaim)->getTable())) {
            return null;
        }

        $pending = ReferralClaim::query()
            ->whereIn('status', [ReferralClaim::CLAIMED, ReferralClaim::AWAITING])
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public function fields(): array
    {
        return [
            Field::select('status', __('admin.referral.status'), [
                ReferralClaim::CLAIMED => __('admin.referral.status_claimed'),
                ReferralClaim::AWAITING => __('admin.referral.status_awaiting'),
                ReferralClaim::REWARDED => __('admin.referral.status_rewarded'),
                ReferralClaim::VOIDED => __('admin.referral.status_voided'),
            ])->onIndex()->width(2),
        ];
    }

    public function computed(): array
    {
        return [
            'referrer' => fn (ReferralClaim $claim) => $this->customerName($claim->referrer),
            'referred' => fn (ReferralClaim $claim) => $this->customerName($claim->referred),
            'code' => fn (ReferralClaim $claim) => $claim->code?->code,
            'order' => fn (ReferralClaim $claim) => $claim->order?->reference,
            'order_paid_at' => fn (ReferralClaim $claim) => $claim->qualified_at?->format('d/m/Y'),
            'rewarded_at' => fn (ReferralClaim $claim) => $claim->rewarded_at?->format('d/m/Y'),
            'note' => fn (ReferralClaim $claim) => $this->reasonLabel($claim->voided_reason),
        ];
    }

    public function computedLabels(): array
    {
        return [
            'referrer' => __('admin.referral.referrer'),
            'referred' => __('admin.referral.referred'),
            'code' => __('admin.referral.code'),
            'order' => __('admin.referral.order'),
            'order_paid_at' => __('admin.referral.order_paid_at'),
            'rewarded_at' => __('admin.referral.rewarded_at'),
            'note' => __('admin.referral.note'),
        ];
    }

    public function rowActions(): array
    {
        return [
            RowAction::make('release', __('admin.referral.release'))
                ->icon('check')
                ->primary()
                ->confirm(__('admin.referral.release_confirm'))
                ->when(fn (ReferralClaim $claim) => $claim->status === ReferralClaim::AWAITING)
                ->run(fn (ReferralClaim $claim) => app(ReferralService::class)->settle($claim)),

            RowAction::make('void', __('admin.referral.void'))
                ->icon('x')
                ->confirm(__('admin.referral.void_confirm'))
                ->when(fn (ReferralClaim $claim) => ! $claim->isSettled())
                ->run(fn (ReferralClaim $claim) => app(ReferralService::class)
                    ->void($claim, ReferralService::VOID_MANUAL)),
        ];
    }

    /** Mọi quan hệ hiện trên bảng đều nạp sẵn — bảng không tốn query mỗi dòng. */
    public function indexQuery(Builder $query): Builder
    {
        return $query->with(['referrer', 'referred', 'code', 'order']);
    }

    public function searchable(): array
    {
        return ['status', 'voided_reason'];
    }

    public function defaultSort(): array
    {
        return ['id', 'desc'];
    }

    /** Họ + tên, hoặc gạch khi khách đã bị xoá. */
    protected function customerName(?Customer $customer): string
    {
        if (! $customer) {
            return '—';
        }

        return trim($customer->first_name.' '.$customer->last_name) ?: '#'.$customer->id;
    }

    /**
     * Lý do đóng lượt, dịch được — hoặc null khi lượt còn sống.
     *
     * `match`, không phải `__('admin.referral.reason_'.$reason)`: ba lý do này
     * do code ghi ra, và một lý do mới thêm mà quên dịch thì `match` trả null
     * (cột trống) thay vì in một khoá lang lên màn hình.
     */
    protected function reasonLabel(?string $reason): ?string
    {
        return match ($reason) {
            ReferralService::VOID_RETURNED => __('admin.referral.reason_returned'),
            ReferralService::VOID_NO_REFERRER => __('admin.referral.reason_no_referrer'),
            ReferralService::VOID_MANUAL => __('admin.referral.reason_manual'),
            default => null,
        };
    }
}
