<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/inventory/notify-me — tell me when this SKU is back.
 *
 * Accepts `sku_id` (preferred) or `variant_id` (backward-compat alias).
 */
class NotifyMeRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sku_id' => ['required_without:variant_id', 'integer'],
            'variant_id' => ['required_without:sku_id', 'integer'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
