<?php

namespace Modules\Hook\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Install record for a plugin (the `plugins` table). Not the source of "should
 * it load" — that's the config allow-list — only the source of "is it installed
 * and at what version", so lifecycle commands stay idempotent.
 *
 * @property string $plugin_id
 * @property ?string $version
 * @property bool $active
 * @property ?\Illuminate\Support\Carbon $installed_at
 */
class PluginState extends Model
{
    protected $table = 'plugins';

    protected $fillable = ['plugin_id', 'version', 'active', 'settings', 'installed_at'];

    protected $casts = [
        'active' => 'bool',
        'settings' => 'array',
        'installed_at' => 'datetime',
    ];
}
