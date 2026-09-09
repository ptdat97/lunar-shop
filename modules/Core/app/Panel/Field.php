<?php

namespace Modules\Core\Panel;

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

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'options' => $this->options,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'columns' => $this->columns,
            'required' => in_array('required', $this->rules, true),
            ...$this->meta,
        ];
    }
}
