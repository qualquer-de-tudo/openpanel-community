<?php

use QOpenPanel\Community\Providers\QOpenPanelServiceProvider;

return [
    'maintenance_path' => env('OPENPANEL_CONTROL_PATH'),
    'path' => env('QOPENPANEL_PATH', 'qopenpanel'),
    'setup' => ['name' => env('QOPENPANEL_ADMIN_NAME', ''), 'email' => env('QOPENPANEL_ADMIN_EMAIL', '')],
    'catalog' => json_decode(file_get_contents(QOpenPanelServiceProvider::path('resources/data/catalog.json')), true, 512, JSON_THROW_ON_ERROR),
    'release' => json_decode(file_get_contents(QOpenPanelServiceProvider::path('resources/data/release.json')), true, 512, JSON_THROW_ON_ERROR),
    'locales' => ['default' => env('QOPENPANEL_LOCALE', 'pt_BR'), 'languages' => json_decode(file_get_contents(QOpenPanelServiceProvider::path('resources/data/locales.json')), true, 512, JSON_THROW_ON_ERROR)],
    'templates' => ['path' => QOpenPanelServiceProvider::path('resources/templates')],
];
