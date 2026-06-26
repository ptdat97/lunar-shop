<?php

namespace Modules\Hook\Console;

use Illuminate\Console\Command;
use Modules\Hook\Plugin\PluginManager;

class PluginUninstallCommand extends Command
{
    protected $signature = 'plugin:uninstall {id : The plugin id} {--force : Skip the confirmation}';

    protected $description = 'Uninstall a plugin: deactivate, roll back its data, drop the install record.';

    public function handle(PluginManager $manager): int
    {
        $id = $this->argument('id');

        if (! $this->option('force') && ! $this->confirm("This rolls back [{$id}]'s data. Continue?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        [$ok, $message] = $manager->uninstall($id);

        $ok ? $this->info($message) : $this->error($message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
