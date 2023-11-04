<?php

// config for Onuraycicek/DatabaseUpdater
return [
    'database_name' => env('DB_DATABASE', 'database'),
    'all_value_is_nullable' => false,
    'backup' => [
        'command' => 'mysqldump',
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
        'host' => env('DB_HOST'),
        'database' => env('DB_DATABASE'),
        'path' => storage_path().'/app/backup/',
    ],
];
