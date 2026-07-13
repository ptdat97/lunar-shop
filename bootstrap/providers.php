<?php

return [
    /*
     * Lunar (forked in-repo under modules/Lunar + modules/LunarAdmin) is no
     * longer a composer package, so its providers aren't auto-discovered —
     * register them here, before the app providers that build on them
     * (ModulesServiceProvider configures the Lunar panel in register()).
     */
    Lunar\LunarServiceProvider::class,
    Lunar\Admin\LunarPanelProvider::class,

    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\ModulesServiceProvider::class,
];
