<?php

namespace Modules\Checkout\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;

/**
 * Payment gateways.
 *
 * The gateway secrets are write-only here, which the old admin did not do: it
 * read `hash_secret` and `secret_key` straight into the form, so every visit to
 * the settings page shipped live credentials to a browser. They are now
 * `secret()` fields — never sent out, and blank on save means "keep what is
 * stored" rather than "clear it".
 */
class PaymentSettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'payment';
    }

    public function label(): string
    {
        return __('admin.payment.title');
    }

    public function icon(): string
    {
        return 'percent';
    }

    public function priority(): int
    {
        return 40;
    }

    public function fields(): array
    {
        return [
            Field::select('default', __('admin.payment.default'), [
                'cod' => __('admin.payment.method_cod'),
                'bank-transfer' => __('admin.payment.method_bank'),
                'vnpay' => 'VNPay',
                'momo' => 'MoMo',
            ])->required()->help(__('admin.payment.default_help')),

            Field::text('vnpay.tmn_code', 'VNPay — TMN Code')->width(6),
            Field::secret('vnpay.hash_secret', 'VNPay — Hash Secret')->width(6),
            Field::text('vnpay.payment_url', 'VNPay — '.__('admin.payment.payment_url'))->rules('url')->width(6),
            Field::text('vnpay.return_url', 'VNPay — '.__('admin.payment.return_url'))->rules('url')->width(6),
            // Endpoint HOÀN TIỀN / tra cứu giao dịch. Trước đây KHÔNG có trường
            // này: admin chuyển thanh toán sang production qua panel, còn hoàn
            // tiền vẫn nằm ở mặc định — tức là SANDBOX. Tiền vào thật, hoàn thì
            // gọi vào môi trường thử.
            Field::text('vnpay.api_url', 'VNPay — '.__('admin.payment.api_url'))
                ->help(__('admin.payment.api_url_help'))
                ->rules('url')->width(6),

            Field::text('momo.partner_code', 'MoMo — Partner Code')->width(4),
            Field::text('momo.access_key', 'MoMo — Access Key')->width(4),
            Field::secret('momo.secret_key', 'MoMo — Secret Key')->width(4),
            Field::text('momo.endpoint', 'MoMo — '.__('admin.payment.endpoint'))->rules('url')->width(4),
            Field::text('momo.return_url', 'MoMo — '.__('admin.payment.return_url'))->rules('url')->width(4),
            Field::text('momo.ipn_url', 'MoMo — '.__('admin.payment.ipn_url'))->rules('url')->width(4),
            // Cùng lý do như vnpay.api_url.
            Field::text('momo.refund_url', 'MoMo — '.__('admin.payment.refund_url'))
                ->help(__('admin.payment.api_url_help'))
                ->rules('url')->width(4),
        ];
    }

    /**
     * Blank gateway keys are dropped rather than stored as empty strings: the
     * drivers treat "configured" as "the key is present", and an empty string
     * would make an unconfigured gateway look ready.
     */
    protected function payload(array $data): array
    {
        return [
            'default' => (string) data_get($data, 'default', 'cod'),
            'vnpay' => array_filter((array) data_get($data, 'vnpay', []), fn ($v) => filled($v)),
            'momo' => array_filter((array) data_get($data, 'momo', []), fn ($v) => filled($v)),
        ];
    }
}
