<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Modules\Platform\Plugin\PluginManager;
use Modules\Platform\Support\PayloadContract;

class PluginDoctorCommand extends Command
{
    protected $signature = 'plugin:doctor';

    protected $description = 'Check enabled plugins for unmet requirements (no changes made).';

    public function handle(PluginManager $manager): int
    {
        $this->line("Payload contract version: <info>" . PayloadContract::VERSION . "</info>");

        $issues = $manager->diagnose();

        if (! $issues) {
            $this->info('All enabled plugins are healthy.');

            return self::SUCCESS;
        }

        $this->table(['Plugin', 'Issue'], array_map(
            fn (array $i) => [$i['plugin'], $i['issue']],
            $issues,
        ));

        $this->error(count($issues) . ' issue(s) found.');

        return self::FAILURE;
    }
}
