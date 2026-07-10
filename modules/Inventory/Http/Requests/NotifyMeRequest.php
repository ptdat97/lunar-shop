<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/inventory/notify-me — tell me when this variant is back.
 */
class NotifyMeRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
