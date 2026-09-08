<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lunar\Core\FieldTypes\Text;
use Lunar\Core\FieldTypes\TranslatedText;
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

    private function optionNamed(array $name): ProductOption
    {
        $option = ProductOption::factory()->create(['name' => $name]);

        // Write the column straight through: the model casts would normalise the
        // shapes this test is specifically about.
        DB::table(config('lunar.database.table_prefix').'product_options')
            ->where('id', $option->id)
            ->update(['name' => json_encode($name)]);

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

    /** attribute_data is the other storage shape, and carries several fields. */
    public function test_attribute_data_gaps_are_reported_per_field(): void
    {
        $this->seedBaseData();

        $product = Product::factory()->create([
            'attribute_data' => [
                'name' => new TranslatedText(collect([
                    'en' => new Text('Cotton Tee'),
                    'vi' => new Text('Áo thun cotton'),
                ])),
                'description' => new TranslatedText(collect([
                    'en' => new Text('Soft and light.'),
                ])),
            ],
        ]);

        $rows = $this->report()->run(['en', 'vi'])
            ->filter(fn (array $r) => $r['table'] === 'products' && $r['id'] === $product->id);

        // Only `description` is short a locale; `name` is complete.
        $this->assertSame(['description'], $rows->pluck('field')->all());
        $this->assertSame(['vi'], $rows->first()['missing']);
    }

    /** A plain Text field is not multilingual — its absence is not a gap. */
    public function test_a_non_translated_field_is_ignored(): void
    {
        $this->seedBaseData();

        $product = Product::factory()->create([
            'attribute_data' => ['name' => new Text('Cotton Tee')],
        ]);

        $this->assertCount(
            0,
            $this->report()->run(['en', 'vi'])
                ->filter(fn (array $r) => $r['table'] === 'products' && $r['id'] === $product->id)
        );
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
