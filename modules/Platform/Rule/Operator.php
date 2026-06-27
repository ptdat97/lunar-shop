<?php

namespace Modules\Platform\Rule;

/**
 * The comparison operators a Rule supports. Generic + side-effect-free: each
 * compares a context-derived value against the rule's literal. Business meaning
 * lives in the resolvers (what "cart.subtotal" is), not here.
 */
enum Operator: string
{
    case Eq = '==';
    case Neq = '!=';
    case Gt = '>';
    case Gte = '>=';
    case Lt = '<';
    case Lte = '<=';
    case In = 'in';
    case NotIn = 'not_in';
    case Contains = 'contains';

    /**
     * Compare $actual (from context) against $expected (the rule literal).
     */
    public function compare(mixed $actual, mixed $expected): bool
    {
        return match ($this) {
            self::Eq => $actual == $expected,
            self::Neq => $actual != $expected,
            self::Gt => $actual > $expected,
            self::Gte => $actual >= $expected,
            self::Lt => $actual < $expected,
            self::Lte => $actual <= $expected,
            self::In => in_array($actual, (array) $expected, false),
            self::NotIn => ! in_array($actual, (array) $expected, false),
            self::Contains => is_iterable($actual) && in_array($expected, (array) $actual, false),
        };
    }
}
