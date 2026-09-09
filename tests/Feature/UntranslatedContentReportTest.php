<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lunar\Core\Enums\FieldTypeEnum;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\FieldTypes\TranslatedText;
use Lunar\Core\Models\Attribute;
use Lunar\Core\Models\AttributeGroup;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Modules\Core\Support\UntranslatedContentReport;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Reports catalog content missing a translation for a served locale (§16).
 *
 * The gap this closes: a blank translation is silent. Lunar falls back to
 * another locale, so nothing throws and nothing logs — a Vietnamese page just
 * shows an English name and nobody notices until a customer does.
 */
class UntranslatedContentReportTest extends TestCase
{
    use CreatesStorefrontData;

    private function report(): UntranslatedContentReport
    {
        return app(UntranslatedContentReport::class);
    }

    /**
     * An option whose `name` is exactly the given map. `label` is translated in
     * full deliberately: the report scans it too since Lunar 2.0, and the
     * factory fills it in English only — which would put a second row against
     * every option and mask what these tests are asserting about `name`.
     */
    private function optionNamed(array $name): ProductOption
    {
        $option = ProductOption::factory()->create(['name' => $name]);

        // Write the columns straight through: the model casts would normalise
        // the shapes this test is specifically about (a blank locale is dropped
        // on read by FilledTranslations, and that is the shape under test).
        DB::table(config('lunar.database.table_prefix').'product_options')
            ->where('id', $option->id)
            ->update([
                'name' => json_encode($name),
                'label' => json_encode(['en' => 'Size', 'vi' => 'Kích cỡ']),
            ]);

        return $option;
    }

    public function test_a_missing_locale_is_reported(): void
    {
        $this->seedBaseData();
        $option = $this->optionNamed(['en' => 'Size']);

        $row = $this->report()->run(['en', 'vi'])
            ->firstWhere(fn (array $r) => $r['table'] === 'product_options' && $r['id'] === $option->id);

        $this->assertNotNull($row, 'An option named only in English was not reported.');
        $this->assertSame(['vi'], $row['missing']);
        $this->assertSame('Size', $row['sample'], 'The report should show what the storefront falls back to.');
    }

    /**
     * A key that exists but is blank is the exact shape that used to render an
     * empty label — it must count as missing, not as present.
     */
    public function test_a_blank_locale_counts_as_missing(): void
    {
        $this->seedBaseData();
        $option = $this->optionNamed(['en' => 'Size', 'vi' => '']);

        $row = $this->report()->run(['en', 'vi'])
            ->firstWhere(fn (array $r) => $r['table'] === 'product_options' && $r['id'] === $option->id);

        $this->assertNotNull($row);
        $this->assertSame(['vi'], $row['missing']);
    }

    public function test_a_fully_translated_record_is_not_reported(): void
    {
        $this->seedBaseData();
        $option = $this->optionNamed(['en' => 'Size', 'vi' => 'Kích cỡ']);

        $this->assertNull(
            $this->report()->run(['en', 'vi'])
                ->firstWhere(fn (array $r) => $r['table'] === 'product_options' && $r['id'] === $option->id)
        );
    }

    /**
     * Nothing filled anywhere is an empty record, not a translation gap. There
     * is no source to translate from and nothing rendered for anyone to notice.
     */
    public function test_an_entirely_empty_record_is_not_reported(): void
    {
        $this->seedBaseData();
        $option = $this->optionNamed(['en' => '', 'vi' => '']);

        $this->assertNull(
            $this->report()->run(['en', 'vi'])
                ->firstWhere(fn (array $r) => $r['table'] === 'product_options' && $r['id'] === $option->id)
        );
    }

    /**
     * Since Lunar 2.0 a product's own copy lives in JSON columns, one report row
     * per column that is short a locale.
     */
    public function test_column_gaps_are_reported_per_field(): void
    {
        $this->seedBaseData();

        $product = Product::factory()->create([
            'name' => ['en' => 'Cotton Tee', 'vi' => 'Áo thun cotton'],
            'description' => ['en' => 'Soft and light.'],
            'short_description' => ['en' => 'Soft.', 'vi' => 'Mềm.'],
        ]);

        $rows = $this->report()->run(['en', 'vi'])
            ->filter(fn (array $r) => $r['table'] === 'products' && $r['id'] === $product->id);

        // Only `description` is short a locale; the other two are complete.
        $this->assertSame(['description'], $rows->pluck('field')->all());
        $this->assertSame(['vi'], $rows->first()['missing']);
    }

    /**
     * attribute_data is the other storage shape — still where custom fields
     * live (meta_title and friends) now that name/description have moved out.
     */
    public function test_attribute_data_gaps_are_reported_per_field(): void
    {
        $this->seedBaseData();

        $product = $this->completeProduct([
            'attribute_data' => [
                'meta_title' => new TranslatedText(collect([
                    'en' => new Text('Cotton Tee'),
                    'vi' => new Text('Áo thun cotton'),
                ])),
                'meta_description' => new TranslatedText(collect([
                    'en' => new Text('Soft and light.'),
                ])),
            ],
        ]);

        $rows = $this->report()->run(['en', 'vi'])
            ->filter(fn (array $r) => $r['table'] === 'products' && $r['id'] === $product->id);

        $this->assertSame(['meta_description'], $rows->pluck('field')->all());
        $this->assertSame(['vi'], $rows->first()['missing']);
    }

    /**
     * A plain Text field is not multilingual — its absence is not a gap.
     *
     * The attribute has to be created here rather than reusing one of the SEO
     * fields: those are `translated_text`, and since Lunar 2.0 the row itself no
     * longer records its type — the report reads it from `lunar_attributes`, so
     * the type has to be real.
     */
    public function test_a_non_translated_field_is_ignored(): void
    {
        $this->seedBaseData();

        $attribute = Attribute::create([
            'attribute_group_id' => AttributeGroup::firstOrCreate(
                ['handle' => 'seo'],
                ['name' => 'SEO', 'position' => 99],
            )->id,
            'handle' => 'care_label',
            'name' => 'Care label',
            'type' => FieldTypeEnum::Text->value,
        ]);
        $attribute->models()->create(['model_type' => Product::morphName()]);

        $product = $this->completeProduct([
            'attribute_data' => ['care_label' => new Text('Machine wash cold')],
        ]);

        $this->assertCount(
            0,
            $this->report()->run(['en', 'vi'])
                ->filter(fn (array $r) => $r['table'] === 'products' && $r['id'] === $product->id)
        );
    }

    /**
     * A product whose own columns are fully translated, so the only rows a test
     * can see are the ones it put in `attribute_data`. Without this the factory's
     * English-only name/description/short_description report three gaps of their
     * own and drown the assertion.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function completeProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'name' => ['en' => 'Cotton Tee', 'vi' => 'Áo thun cotton'],
            'description' => ['en' => 'Soft and light.', 'vi' => 'Mềm và nhẹ.'],
            'short_description' => ['en' => 'Soft.', 'vi' => 'Mềm.'],
            ...$attributes,
        ]);
    }

    public function test_a_single_locale_shop_reports_nothing(): void
    {
        $this->seedBaseData();
        $this->optionNamed(['en' => 'Size']);

        $this->assertTrue($this->report()->run(['en'])->isEmpty());
    }

    /** The command exits non-zero on gaps so it can gate a release. */
    public function test_the_command_fails_when_gaps_exist(): void
    {
        $this->seedBaseData();
        $this->optionNamed(['en' => 'Size']);

        $this->artisan('content:untranslated', ['--locale' => ['en', 'vi']])
            ->assertExitCode(1);
    }

    public function test_the_command_succeeds_when_everything_is_translated(): void
    {
        $this->seedBaseData();

        // Translate whatever the base seed left behind, so the only remaining
        // state is "fully translated".
        foreach ($this->report()->run(['en', 'vi']) as $row) {
            $table = config('lunar.database.table_prefix').$row['table'];

            if ($row['field'] !== 'name' || ! str_contains($row['table'], 'option')) {
                continue;
            }

            DB::table($table)->where('id', $row['id'])
                ->update(['name' => json_encode(['en' => $row['sample'], 'vi' => $row['sample']])]);
        }

        $remaining = $this->report()->run(['en', 'vi']);

        $this->assertTrue(
            $remaining->isEmpty(),
            'Base seed left gaps outside product options: '.$remaining->pluck('table')->unique()->implode(', '),
        );

        $this->artisan('content:untranslated', ['--locale' => ['en', 'vi']])
            ->assertExitCode(0);
    }
}
