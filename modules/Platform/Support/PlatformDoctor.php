<?php

namespace Modules\Platform\Support;

use Modules\Platform\Models\Workflow;
use Modules\Platform\Rule\RuleRegistry;
use Modules\Platform\Workflow\WorkflowContract;
use Modules\Platform\Workflow\WorkflowRegistry;

/**
 * Read-only platform health check: surfaces drift between persisted config
 * (workflows) and the live registries (triggers/actions/rule fields), plus
 * structural problems in stored definitions. Powers `platform:doctor`. No
 * side effects — never changes anything.
 */
class PlatformDoctor
{
    public function __construct(
        protected WorkflowRegistry $workflows,
        protected RuleRegistry $rules,
    ) {}

    /**
     * @return list<array{scope:string, ref:string, issue:string}>
     */
    public function diagnose(): array
    {
        $issues = [];

        foreach (Workflow::all() as $workflow) {
            $ref = $workflow->name ?: "#{$workflow->id}";

            // Structural validity (shape/operator/match) via the contract.
            foreach (WorkflowContract::validate([
                'trigger' => $workflow->trigger,
                'action' => $workflow->action,
                'conditions' => $workflow->conditions,
            ]) as $error) {
                $issues[] = ['scope' => 'workflow', 'ref' => $ref, 'issue' => $error];
            }

            // Live-registry drift: trigger/action must be registered.
            if (! $this->workflows->hasTrigger($workflow->trigger)) {
                $issues[] = ['scope' => 'workflow', 'ref' => $ref,
                    'issue' => "trigger [{$workflow->trigger}] is not registered (plugin disabled?)"];
            }

            if (! array_key_exists($workflow->action, $this->workflows->actionLabels())) {
                $issues[] = ['scope' => 'workflow', 'ref' => $ref,
                    'issue' => "action [{$workflow->action}] is not registered (plugin disabled?)"];
            }

            // Condition fields must resolve.
            foreach (($workflow->conditions['rules'] ?? []) as $rule) {
                $field = $rule['field'] ?? null;
                if ($field && ! $this->rules->has($field)) {
                    $issues[] = ['scope' => 'workflow', 'ref' => $ref,
                        'issue' => "rule field [{$field}] is not registered"];
                }
            }
        }

        return $issues;
    }
}
