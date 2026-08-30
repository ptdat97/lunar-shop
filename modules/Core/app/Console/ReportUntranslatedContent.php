<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Modules\Core\Support\UntranslatedContentReport;

/**
 * Lists catalog content missing a translation for a locale the shop serves
 * (roadmap §16).
 *
 * A blank translation is silent: Lunar falls back to another locale, so nothing
 * throws and nothing logs — a Vietnamese page just quietly shows an English
 * name. This is the only thing that makes that visible.
 *
 * Exit code 1 when gaps exist, so it can gate a release or run from cron
 * alongside the schedule heartbeat.
 */
class ReportUntranslatedContent extends Command
{
    protected $signature = 'content:untranslated
        {--locale=* : Locales to check (default: every locale the storefront serves)}
        {--json : Machine-readable output}
        {--limit=50 : Rows to print; the summary always counts everything}';

    protected $description = 'List catalog content missing a translation for a served locale';

    public function handle(UntranslatedContentReport $report): int
    {
        $locales = $this->option('locale') ?: $report->locales();

        if (count($locales) < 2) {
            $this->info('Only one locale is served — nothing to compare against.');

            return self::SUCCESS;
        }

        $rows = $report->run($locales);

        if ($this->option('json')) {
            $this->line((string) json_encode($rows->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $rows->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        if ($rows->isEmpty()) {
            $this->info(sprintf('No gaps across %s.', implode(', ', $locales)));

            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));

        $this->table(
            ['Table', 'ID', 'Field', 'Missing', 'Falls back to'],
            $rows->take($limit)->map(fn (array $row) => [
                $row['table'],
                $row['id'],
                $row['field'],
                implode(', ', $row['missing']),
                Str::limit($row['sample'], 40),
            ])->all(),
        );

        if ($rows->count() > $limit) {
            $this->line(sprintf('… and %d more.', $rows->count() - $limit));
        }

        // Grouped by locale: "vi is missing on 12 records" is the number a shop
        // owner acts on, not a list of ids.
        $this->newLine();
        foreach ($locales as $locale) {
            $count = $rows->filter(fn (array $row) => in_array($locale, $row['missing'], true))->count();

            if ($count > 0) {
                $this->warn(sprintf('%s: %d record(s) with no translation.', $locale, $count));
            }
        }

        return self::FAILURE;
    }
}
