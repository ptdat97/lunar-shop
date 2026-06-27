<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Modules\Platform\Support\PayloadContract;
use Modules\Platform\Support\PlatformDoctor;
use Modules\Platform\Workflow\WorkflowContract;

class PlatformDoctorCommand extends Command
{
    protected $signature = 'platform:doctor';

    protected $description = 'Health-check the platform: workflow/registry drift (no changes made).';

    public function handle(PlatformDoctor $doctor): int
    {
        $this->line('Payload contract:  <info>'.PayloadContract::VERSION.'</info>');
        $this->line('Workflow contract: <info>'.WorkflowContract::VERSION.'</info>');

        $issues = $doctor->diagnose();

        if (! $issues) {
            $this->info('Platform healthy — no drift detected.');

            return self::SUCCESS;
        }

        $this->table(['Scope', 'Ref', 'Issue'], array_map(
            fn (array $i) => [$i['scope'], $i['ref'], $i['issue']],
            $issues,
        ));

        $this->error(count($issues).' issue(s) found.');

        return self::FAILURE;
    }
}
