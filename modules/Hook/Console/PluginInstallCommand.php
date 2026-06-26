<?php

namespace Modules\Hook\Console;

use Illuminate\Console\Command;
use Modules\Hook\Plugin\PluginManager;

class PluginInstallCommand extends Command
{
    protected $signature = 'plugin:install {id : The plugin id, e.g. acme/loyalty}';

    protected $description = 'Install (migrate + activate) a plugin. Idempotent.';

    public function handle(PluginManager $manager): int
    {
        [$ok, $message] = $manager->install($this->argument('id'));

        $ok ? $this->info($message) : $this->error($message);

        if ($ok && ! in_array($this->argument('id'), (array) config('plugins.enabled'), true)) {
            $this->warn("Add '{$this->argument('id')}' to config('plugins.enabled') so it loads on boot.");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
