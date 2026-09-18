<?php

namespace Modules\Promotion\Panel;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\PanelResource;
use Modules\Promotion\Models\LoyaltyEntry;
use Modules\Promotion\Services\LoyaltyService;

/**
 * Sổ cái điểm — **chỉ đọc**.
 *
 * Không sửa, không xoá, không tạo. Đó là toàn bộ lý do sổ cái tồn tại: một dòng
 * đã ghi là một sự kiện đã xảy ra. Muốn đổi số dư thì ghi thêm một bút toán
 * (`LoyaltyService::adjust()`), không phải sửa dòng cũ — sửa dòng cũ là làm cho
 * số dư không còn đối soát lại được, đúng thứ mà một cột `points_balance` đã
 * gây ra và là lý do nó không được chọn.
 *
 * Màn hình này trả lời đúng một câu hỏi mà không có nó thì không ai trả lời
 * được: *"vì sao khách này có ngần này điểm?"*
 */
class LoyaltyEntryResource extends PanelResource
{
    public function model(): string
    {
        return LoyaltyEntry::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    public function navigationGroup(): string
    {
        return 'sales';
    }

    public function navigationPriority(): int
    {
        return 80;
    }

    public function key(): string
    {
        return 'loyalty-entries';
    }

    public function label(): string
    {
        return __('admin.loyalty.plural');
    }

    public function singular(): string
    {
        return __('admin.loyalty.label');
    }

    /** Sổ cái là một tập bút toán — `fileText`. Panel không có icon `gift`. */
    public function icon(): string
    {
        return 'fileText';
    }

    /** Cùng quyền với đơn hàng: đây là việc chăm sóc khách. */
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

    public function fields(): array
    {
        return [
            Field::select('type', __('admin.loyalty.type'), [
                LoyaltyEntry::EARN => __('admin.loyalty.type_earn'),
                LoyaltyEntry::SPEND => __('admin.loyalty.type_spend'),
                LoyaltyEntry::EXPIRE => __('admin.loyalty.type_expire'),
                LoyaltyEntry::REVOKE => __('admin.loyalty.type_revoke'),
                LoyaltyEntry::REFUND => __('admin.loyalty.type_refund'),
                LoyaltyEntry::ADJUST => __('admin.loyalty.type_adjust'),
            ])->onIndex()->width(2),

            Field::number('points', __('admin.loyalty.points'))->onIndex()->width(2),
        ];
    }

    /**
     * `customer` và `order` eager-load để bảng không tốn một truy vấn mỗi dòng;
     * mới nhất lên đầu vì câu hỏi thường trực là "vừa có chuyện gì".
     */
    public function indexQuery(Builder $query): Builder
    {
        return $query->with(['customer', 'order'])->latest('id');
    }

    public function computed(): array
    {
        return [
            'customer' => fn (LoyaltyEntry $entry) => trim(
                ($entry->customer?->first_name ?? '').' '.($entry->customer?->last_name ?? '')
            ) ?: '—',
            'order' => fn (LoyaltyEntry $entry) => $entry->order?->reference ?? '—',
            // Phần chưa bị ăn của lô — cột duy nhất KHÔNG đọc thẳng từ cột nào,
            // vì nó là thứ dẫn xuất. Debit không phải lô nên để trống.
            'remaining' => fn (LoyaltyEntry $entry) => $entry->points > 0
                ? (string) app(LoyaltyService::class)->lotRemaining($entry)
                : '—',
            'available_at' => fn (LoyaltyEntry $entry) => $entry->available_at?->format('d/m/Y') ?? '—',
            'expires_at' => fn (LoyaltyEntry $entry) => $entry->expires_at?->format('d/m/Y') ?? '—',
        ];
    }

    public function computedLabels(): array
    {
        return [
            'customer' => __('admin.loyalty.customer'),
            'order' => __('admin.loyalty.order'),
            'remaining' => __('admin.loyalty.remaining'),
            'available_at' => __('admin.loyalty.available_at'),
            'expires_at' => __('admin.loyalty.expires_at'),
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }
}
