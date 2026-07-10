<?php

namespace Modules\Notification\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Notification\Models\DeviceToken;

/**
 * A registered push target.
 *
 * @mixin DeviceToken
 */
class DeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // The token itself is never echoed back: it is a push credential, and
        // the caller already has it.
        return [
            'id' => $this->id,
            'platform' => $this->platform,
        ];
    }
}
