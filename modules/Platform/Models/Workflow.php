<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Platform\Rule\RuleSet;

/**
 * A workflow rule: trigger → conditions → action. Stored as JSON so it can be
 * authored in the admin (no code). The engine reads enabled rows for a fired
 * trigger and runs them.
 *
 * @property string $name
 * @property string $trigger
 * @property ?array $conditions
 * @property string $action
 * @property ?array $action_config
 * @property bool $enabled
 */
class Workflow extends Model
{
    protected $table = 'workflows';

    protected $fillable = ['name', 'trigger', 'conditions', 'action', 'action_config', 'enabled'];

    protected $casts = [
        'conditions' => 'array',
        'action_config' => 'array',
        'enabled' => 'bool',
    ];

    /** The condition set (empty set = always passes). */
    public function ruleSet(): RuleSet
    {
        return RuleSet::fromArray($this->conditions ?? []);
    }
}
