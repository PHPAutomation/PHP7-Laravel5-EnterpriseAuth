<?php

return [
    'routes' => [
        'login' => 'login/microsoft',
        'callback' => 'login/microsoft/callback',
    ],
    'credentials' => [
        'client_id' => env('AZURE_AD_CLIENT_ID', ''),
        'client_secret' => env('AZURE_AD_CLIENT_SECRET', ''),
        'redirect' => '/login/microsoft/callback'
    ]
];
