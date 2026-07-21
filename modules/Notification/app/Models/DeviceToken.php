<?php

namespace Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A push target registered by the mobile app.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 * @property ?string $device_name
 */
class DeviceToken extends Model
{
    protected $table = 'device_tokens';

    protected $guarded = [];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /** Platforms the app may register. */
    public const PLATFORMS = ['ios', 'android', 'web'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
