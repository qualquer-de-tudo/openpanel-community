<?php

return [
    'default' => env('APP_LOCALE', 'pt_BR'),
    'languages' => json_decode(file_get_contents(resource_path('data/locales.json')), true, 512, JSON_THROW_ON_ERROR),
];
