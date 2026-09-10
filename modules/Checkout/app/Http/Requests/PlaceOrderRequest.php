<?php

namespace Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Checkout\Services\CheckoutService;
use Modules\Shipping\Services\PickupLocation;

/**
 * The single SSR checkout form: address + shipping + payment in one submit.
 */
class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CheckoutService $checkout, PickupLocation $pickup): array
    {
        // Collecting at the counter has no delivery address to give. The
        // storefront hides those fields, but hiding a field is not a guard
        // (standards §17.4) — they are dropped here, and only when the shop
        // actually offers collection. Posting shipping_option=pickup at a shop
        // that has it switched off still has to satisfy the address rules, and
        // CheckoutService::setPickup() refuses it a second time.
        $collecting = $pickup->isAvailable()
            && $this->input('shipping_option') === PickupLocation::IDENTIFIER;

        $address = $collecting ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'];

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'line_one' => $address,
            'state' => $address,   // Tỉnh/Thành
            'city' => $address,    // Phường/Xã
            'country_id' => ['required', 'integer'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'shipping_option' => ['required', 'string'],
            'payment_type' => ['required', 'string', Rule::in($checkout->paymentMethods())],
            // Dấu vân tay của giỏ lúc trang được render. `nullable` chứ không
            // `required`: client API hiện có chưa gửi nó, và bắt buộc ngay sẽ
            // làm hỏng app đang chạy. Gửi thì được kiểm; không gửi thì mất lớp
            // bảo vệ này chứ không mất khả năng đặt hàng.
            'fingerprint' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** Is this submission collecting at the counter? */
    public function isCollecting(): bool
    {
        return $this->input('shipping_option') === PickupLocation::IDENTIFIER;
    }

    /**
     * Address fields only (drops the shipping/payment selections).
     *
     * @return array<string, mixed>
     */
    public function addressData(): array
    {
        return collect($this->validated())
            ->except(['shipping_option', 'payment_type'])
            ->all();
    }
}
