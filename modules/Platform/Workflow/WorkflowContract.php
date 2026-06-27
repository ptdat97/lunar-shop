<?php

namespace Modules\Platform\Workflow;

use Modules\Platform\Rule\Operator;
use Modules\Platform\Rule\RuleSet;

/**
 * Versioning + validation anchor for workflow/rule definitions (the JSON stored
 * on the `workflows` table), mirroring PayloadContract for hook payloads. A
 * persisted definition is a long-lived contract: bump VERSION and update the
 * rules deliberately when the shape changes, so old rows can be detected and
 * migrated rather than silently breaking.
 *
 * validate() is a pure, structural check (no DB, no business) — used by
 * platform:doctor and before persisting a workflow.
 */
final class WorkflowContract
{
    /** Definition schema version. Bump on a breaking shape change. */
    public const VERSION = '1.0';

    /**
     * Structural problems with a workflow definition. Empty = valid.
     *
     * @param  array<string, mixed>  $definition  trigger/action/conditions/action_config
     * @return list<string>
     */
    public static function validate(array $definition): array
    {
        $errors = [];

        if (blank($definition['trigger'] ?? null)) {
            $errors[] = 'Missing trigger.';
        }

        if (blank($definition['action'] ?? null)) {
            $errors[] = 'Missing action.';
        }

        $errors = array_merge($errors, self::validateConditions($definition['conditions'] ?? null));

        return $errors;
    }

    /**
     * Validate a serialised RuleSet (conditions). Null/empty is valid (= always).
     *
     * @return list<string>
     */
    public static function validateConditions(mixed $conditions): array
    {
        if (blank($conditions)) {
            return [];
        }

        if (! is_array($conditions)) {
            return ['Conditions must be an object.'];
        }

        $errors = [];

        $match = $conditions['match'] ?? RuleSet::ALL;
        if (! in_array($match, [RuleSet::ALL, RuleSet::ANY], true)) {
            $errors[] = "Invalid match mode [{$match}] (expected all|any).";
        }

        foreach (($conditions['rules'] ?? []) as $i => $rule) {
            if (blank($rule['field'] ?? null)) {
                $errors[] = "Rule #{$i}: missing field.";
            }

            $operator = $rule['operator'] ?? null;
            if (Operator::tryFrom((string) $operator) === null) {
                $errors[] = "Rule #{$i}: invalid operator [{$operator}].";
            }
        }

        return $errors;
    }
}
