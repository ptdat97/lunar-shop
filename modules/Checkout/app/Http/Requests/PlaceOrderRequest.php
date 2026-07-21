<?php

namespace Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Checkout\Services\CheckoutService;

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
    public function rules(CheckoutService $checkout): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'line_one' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],   // Tỉnh/Thành
            'city' => ['required', 'string', 'max:255'],    // Phường/Xã
            'country_id' => ['required', 'integer'],
            'contact_email' => ['required', 'email', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'shipping_option' => ['required', 'string'],
            'payment_type' => ['required', 'string', Rule::in($checkout->paymentMethods())],
        ];
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
