<?php

namespace Modules\Hook\Console;

use Illuminate\Console\Command;
use Modules\Hook\Plugin\PluginManager;

class PluginDisableCommand extends Command
{
    protected $signature = 'plugin:disable {id : The plugin id}';

    protected $description = 'Deactivate a plugin but keep its data.';

    public function handle(PluginManager $manager): int
    {
        [$ok, $message] = $manager->disable($this->argument('id'));

        $ok ? $this->info($message) : $this->error($message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
