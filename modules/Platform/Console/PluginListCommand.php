<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Modules\Platform\Plugin\PluginManager;

class PluginListCommand extends Command
{
    protected $signature = 'plugin:list';

    protected $description = 'List discovered plugins and their state (enabled / installed / active).';

    public function handle(PluginManager $manager): int
    {
        $rows = collect($manager->status())->map(fn (array $p) => [
            $p['id'],
            $p['version'],
            $p['enabled'] ? 'yes' : 'no',
            $p['installed'] ? 'yes' : 'no',
            $p['active'] ? 'yes' : 'no',
            $p['satisfied'] ? 'ok' : 'NO',
        ])->all();

        if (! $rows) {
            $this->info('No plugins discovered.');

            return self::SUCCESS;
        }

        $this->table(['Plugin', 'Version', 'Enabled', 'Installed', 'Active', 'Requires'], $rows);

        return self::SUCCESS;
    }
}
