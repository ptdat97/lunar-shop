<?php

namespace Modules\Core\Panel;

use Illuminate\Validation\ValidationException;

/**
 * Turns what a form posted into what a column expects.
 *
 * Two field types do not round-trip as themselves: a JSON column is edited as
 * text, and a tag list is edited as one comma-separated line. Both controllers
 * that accept a declared form need the same conversion, and having each know
 * about field types separately is how one of them ends up not knowing about the
 * next one added — which is exactly what happened to `tags`.
 */
class InputNormaliser
{
    /**
     * @param  array<int, Field>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $fields, array $data): array
    {
        foreach ($fields as $field) {
            $type = $field->toArray()['type'];

            if ($type === 'tags') {
                $raw = (string) (data_get($data, $field->name) ?? '');

                data_set($data, $field->name, array_values(array_filter(
                    array_map('trim', explode(',', $raw)),
                    fn (string $tag) => $tag !== '',
                )));

                continue;
            }

            if ($type !== 'json') {
                continue;
            }

            $raw = data_get($data, $field->name);

            if (blank($raw)) {
                data_set($data, $field->name, null);

                continue;
            }

            $decoded = json_decode((string) $raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    $field->name => __('panel.invalid_json'),
                ]);
            }

            data_set($data, $field->name, $decoded);
        }

        return $data;
    }
}
