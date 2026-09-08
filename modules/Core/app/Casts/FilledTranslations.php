<?php

namespace Modules\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Support\Collection;

/**
 * A `{locale: text}` column that drops locales whose value is blank.
 *
 * Lunar's `HasTranslations::translate()` resolves a locale with
 * `Arr::get($values, $locale, $default)`. When the key exists but holds an empty
 * string, `Arr::get()` returns that empty string rather than the default, so the
 * fallback never runs — a product option named only in English but carrying a
 * blank `vi` key renders as an empty label everywhere the shop runs under `vi`.
 * The bug is unchanged in 2.0.0-alpha.6.
 *
 * Lunar 1.x let us fix this by swapping the model
 * (`ModelManifest::replace()` + a `translate()` override). **2.0 removed model
 * replacement entirely** — core refers to `ProductOptionValue::class` and friends
 * directly, and `ModelManifest` now only registers route bindings and the morph
 * map. So the fix moved one layer down, to the column itself: strip the blank
 * locales on read and upstream's `translate()` is correct as written, because
 * "key exists but is empty" can no longer happen.
 *
 * Installed from a service provider via `Model::addCasts()`
 * (`HasExtendableCasts`), which is the extension seam 2.0 does offer — see
 * CatalogServiceProvider. That also widens the fix: every model reading the
 * column benefits, not just the ones we remembered to subclass.
 *
 * Two container shapes, because core is not consistent: the catalogue models
 * cast these columns to `AsCollection`, the option models to `AsArrayObject`.
 * Match whichever the model already used — admin forms and `toArray()` care
 * about the difference.
 */
class FilledTranslations implements Castable
{
    /** Cast string for a column core declares as `AsCollection`. */
    public static function asCollection(): string
    {
        return static::class.':collection';
    }

    /** Cast string for a column core declares as `AsArrayObject`. */
    public static function asArrayObject(): string
    {
        return static::class.':arrayobject';
    }

    /**
     * {@inheritdoc}
     */
    public static function castUsing(array $arguments)
    {
        $container = $arguments[0] ?? 'collection';

        return new class($container) implements CastsAttributes, SerializesCastableAttributes
        {
            public function __construct(protected string $container) {}

            public function get($model, $key, $value, $attributes)
            {
                if (! isset($attributes[$key])) {
                    return;
                }

                $data = Json::decode($attributes[$key]);

                if (! is_array($data)) {
                    return null;
                }

                // The whole point: a locale present but blank is the same as a
                // locale that was never written.
                $data = array_filter($data, static fn ($translation) => filled($translation));

                return $this->container === 'arrayobject'
                    ? new ArrayObject($data, ArrayObject::ARRAY_AS_PROPS)
                    : new Collection($data);
            }

            public function set($model, $key, $value, $attributes)
            {
                return [$key => Json::encode($value)];
            }

            public function serialize($model, string $key, $value, array $attributes)
            {
                return $value instanceof ArrayObject
                    ? $value->getArrayCopy()
                    : $value?->toArray();
            }
        };
    }
}
