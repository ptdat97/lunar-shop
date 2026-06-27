<?php

namespace Modules\Platform\Rule;

/**
 * Registry of rule "fields" — named resolvers that extract a value from the
 * evaluation context. A field key (e.g. "cart.subtotal", "customer.group") maps
 * to a resolver `fn(array $context): mixed`.
 *
 * Core ships NO business fields — modules/plugins register what each key means
 * (e.g. Promotion registers cart.subtotal). The Rule engine only knows how to
 * resolve a key + compare with an operator, so it stays business-free.
 */
class RuleRegistry
{
    /** @var array<string, callable> key => fn(array $context): mixed */
    protected array $fields = [];

    /**
     * Register a field resolver.
     *
     * @param  callable(array<string,mixed>): mixed  $resolver
     */
    public function field(string $key, callable $resolver): void
    {
        $this->fields[$key] = $resolver;
    }

    public function has(string $key): bool
    {
        return isset($this->fields[$key]);
    }

    /** Registered field keys (e.g. for an admin dropdown). */
    public function keys(): array
    {
        return array_keys($this->fields);
    }

    /**
     * Resolve a field's value from the context. Unknown keys return null (a rule
     * on an unknown field simply won't match equality/“>”, fails closed).
     *
     * @param  array<string, mixed>  $context
     */
    public function resolve(string $key, array $context): mixed
    {
        $resolver = $this->fields[$key] ?? null;

        return $resolver ? $resolver($context) : null;
    }
}
