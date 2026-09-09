<?php

namespace Modules\Core\Panel;

use Illuminate\Database\Eloquent\Model;

/**
 * One editable field in a panel resource form.
 *
 * The whole point of this class is that a screen is *declared*, not written: a
 * resource lists its fields, the generic controller validates and persists from
 * that list, and one Vue form page renders it. Adding an admin screen costs a
 * schema class, not a page of Vue.
 *
 * `type` is the contract with resources/js/panel/components/PanelField.vue —
 * every value here must have a matching branch there.
 */
class Field
{
    /** @var array<int, string> */
    protected array $rules = [];

    /** @var array<string, string> */
    protected array $options = [];

    protected ?string $help = null;

    protected ?string $placeholder = null;

    protected mixed $default = null;

    protected bool $onIndex = false;

    protected int $columns = 12;

    /** @var array<string, mixed> */
    protected array $meta = [];

    /** @var (callable(): array<string|int, string>)|null */
    protected $optionsResolver = null;

    /** @var array<int, Field> */
    protected array $children = [];

    final protected function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type,
    ) {}

    public static function text(string $name, string $label): static
    {
        return new static($name, $label, 'text');
    }

    public static function textarea(string $name, string $label): static
    {
        return new static($name, $label, 'textarea');
    }

    /**
     * Long-form body copy. Rendered as a plain monospace editor: the storefront
     * renders `content` as raw HTML already, so what the admin types is what
     * ships — no WYSIWYG layer to silently rewrite the markup.
     */
    public static function html(string $name, string $label): static
    {
        return new static($name, $label, 'html');
    }

    public static function number(string $name, string $label): static
    {
        return (new static($name, $label, 'number'))->rules('integer');
    }

    public static function toggle(string $name, string $label): static
    {
        return (new static($name, $label, 'toggle'))->rules('boolean')->default(false);
    }

    /** @param array<string, string> $options */
    public static function select(string $name, string $label, array $options): static
    {
        $field = new static($name, $label, 'select');
        $field->options = $options;

        return $field->rules('in:'.implode(',', array_keys($options)));
    }

    /**
     * An image path/URL. Stored as a string the storefront resolves through
     * MediaUrl, so both an uploaded library path and an absolute URL work.
     */
    public static function image(string $name, string $label): static
    {
        return (new static($name, $label, 'image'))->rules('string', 'max:2048');
    }

    /**
     * A slug derived from another field when left blank. The model's `creating`
     * hook still fills it server-side; this only mirrors that in the UI.
     */
    public static function slug(string $name, string $label, string $from): static
    {
        $field = new static($name, $label, 'slug');
        $field->meta['from'] = $from;

        return $field->rules('string', 'max:255');
    }

    /**
     * A free-form JSON object. The last resort for a column whose shape varies
     * per row (page_sections.settings, pages.og_data) — the editor validates
     * that it parses, nothing more.
     */
    public static function json(string $name, string $label): static
    {
        return new static($name, $label, 'json');
    }

    /**
     * A select whose options are rows, resolved when the form is built rather
     * than declared inline — collections, products, promotions.
     *
     * The resolver receives the record being edited, so a picker can be scoped
     * to it — a lookbook item's pin image must come from that lookbook's own
     * photos, not from every photo in the database.
     *
     * @param  callable(?Model): array<string|int, string>  $options
     */
    public static function relation(string $name, string $label, callable $options): static
    {
        $field = new static($name, $label, 'select');
        $field->optionsResolver = $options;

        return $field;
    }

    /**
     * A repeating group of sub-fields, stored as a list of objects. This is
     * what a JSON settings column is usually holding: hero slides, icon boxes,
     * product tabs.
     *
     * @param  array<int, Field>  $fields
     */
    public static function repeater(string $name, string $label, array $fields): static
    {
        $field = new static($name, $label, 'repeater');
        $field->children = $fields;

        return $field->rules('array');
    }

    /**
     * A repeater backed by a hasMany relation rather than a JSON column: each
     * row is a child model. Rows keep their ids across a save, so anything
     * pointing at them (a lookbook item pinned to a photo) survives editing.
     *
     * Row order is the relation's `sort` column, written from the row's
     * position — the admin reorders with the repeater's own arrows instead of
     * typing numbers into a field.
     *
     * @param  array<int, Field>  $fields
     */
    public static function hasMany(string $name, string $label, array $fields): static
    {
        $field = static::repeater($name, $label, $fields);
        $field->meta['relation'] = true;

        return $field;
    }

    public function isRelation(): bool
    {
        return (bool) ($this->meta['relation'] ?? false);
    }

    /**
     * This field is not a column and must never reach a mass assignment — it
     * is assembled by the resource on read and consumed by its saved() hook on
     * write. A menu's item tree is the case: one form field, its own table.
     */
    public function virtual(): static
    {
        $this->meta['virtual'] = true;

        return $this;
    }

    public function isVirtual(): bool
    {
        return (bool) ($this->meta['virtual'] ?? false);
    }

    /** Text on the repeater's add button — "Thêm slide" beats a bare "Thêm". */
    public function addLabel(string $label): static
    {
        $this->meta['addLabel'] = $label;

        return $this;
    }

    /** Which sub-field titles a collapsed repeater row. */
    public function itemLabel(string $field): static
    {
        $this->meta['itemLabel'] = $field;

        return $this;
    }

    /** Let the select hold several values (stored as a JSON list). */
    public function multiple(): static
    {
        $this->meta['multiple'] = true;

        return $this->rules('array');
    }

    /**
     * Show this field only while another field holds one of these values —
     * how a page_sections form shows the slides of a hero slider and nothing
     * else. Purely a display rule: a hidden field is simply not submitted.
     */
    public function visibleWhen(string $field, string ...$values): static
    {
        $this->meta['visibleWhen'] = ['field' => $field, 'values' => $values];

        return $this;
    }

    public function rules(string ...$rules): static
    {
        $this->rules = [...$this->rules, ...$rules];

        return $this;
    }

    public function required(): static
    {
        return $this->rules('required');
    }

    public function nullable(): static
    {
        return $this->rules('nullable');
    }

    public function help(string $help): static
    {
        $this->help = $help;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    /** Also show this field as a column on the index table. */
    public function onIndex(): static
    {
        $this->onIndex = true;

        return $this;
    }

    /** Width on the 12-column form grid. */
    public function width(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function showsOnIndex(): bool
    {
        return $this->onIndex;
    }

    /**
     * Whether this field applies to the given form state. A field hidden by
     * `visibleWhen` is not rendered and not submitted, so it must not be
     * validated either — and two fields may share a name across mutually
     * exclusive branches (a hero slider and a lookbook both store
     * `settings.slides`), which only works while exactly one is live.
     *
     * @param  array<string, mixed>  $input
     */
    public function appliesTo(array $input): bool
    {
        $rule = $this->meta['visibleWhen'] ?? null;

        if (! $rule) {
            return true;
        }

        return in_array((string) data_get($input, $rule['field']), $rule['values'], true);
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    /** @return array<int, string> */
    public function validationRules(): array
    {
        // Every field is optional unless it said otherwise: an admin clearing a
        // subtitle must be able to save, and `sometimes` would let a missing key
        // slip through instead of nulling the column.
        if (! in_array('required', $this->rules, true) && ! in_array('nullable', $this->rules, true)) {
            return [...$this->rules, 'nullable'];
        }

        return $this->rules;
    }

    /** @return array<int, Field> */
    public function children(): array
    {
        return $this->children;
    }

    /** @return array<string, mixed> */
    public function toArray(?Model $record = null): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            // Relation options are resolved here, not at declaration: a schema
            // is built on every request and a query in the constructor would
            // run even for the screens that never render this field.
            'options' => $this->optionsResolver
                ? array_map('strval', ($this->optionsResolver)($record))
                : $this->options,
            'children' => array_map(fn (Field $child) => $child->toArray($record), $this->children),
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'columns' => $this->columns,
            'default' => $this->default,
            'required' => in_array('required', $this->rules, true),
            ...$this->meta,
        ];
    }
}
