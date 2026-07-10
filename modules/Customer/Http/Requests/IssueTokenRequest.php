<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/token — credentials for a bearer token.
 */
class IssueTokenRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** The handset this token belongs to, so the user can tell them apart. */
    public function deviceName(): ?string
    {
        return $this->input('device_name');
    }
}
