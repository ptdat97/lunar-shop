<?php

// Overrides Lunar's lunarpanel::global translations. Laravel replaces the whole
// file (no key merge), so the vendor keys are copied here in full and we add
// 'content' — a navigation group the app introduces (CMS, menus, sections) that
// Lunar core does not ship. Keep in sync with the vendor file when upgrading.
return [

    'sections' => [
        'catalog' => 'Catalog',
        'sales' => 'Sales',
        'reports' => 'Reports',
        'settings' => 'Settings',
        'content' => 'Content',
    ],

];
