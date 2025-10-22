<?php

return [

    'default' => env('FILESYSTEM_DISK', 'media'),

    'disks' => [
        // ... lo que ya tienes

        'media' => [
            'driver' => 'local',
            'root'   => storage_path('app/public/media'),
            'url'    => env('APP_URL').'/storage/media',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
