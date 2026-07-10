<?php

namespace Modules\Notification\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notification\Models\DeviceToken;

/**
 * POST /api/v1/devices — register this device for push.
 */
class RegisterDeviceRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
