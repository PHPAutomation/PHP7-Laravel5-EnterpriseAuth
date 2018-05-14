<?php

return [
    'routes' => [
        // The middleware to wrap the auth routes in.
        // Must contain session handling otherwise login will fail.
        'middleware' => 'web',

        // The url that will redirect to the SSO URL.
        // There should be no reason to override this.
        'login' => 'login/microsoft',

        // The app route that SSO will redirect to.
        // There should be no reason to override this.
        'callback' => 'login/microsoft/callback',
    ],
    // And now for something completely different
    'apiroutes' => [
        // dont use web
        'middleware'     => 'api',
        'authmiddleware' => 'auth:api',
        // and the routes
        'login'              => 'api/login/microsoft',
        'myinfo'             => 'api/me',
        'myroles'            => 'api/me/roles',
        'myrolespermissions' => 'api/me/roles/permissions',
    ],
    // TODO: go around rewriting crap to use these
    'credentials' => [
        'client_id'     => env('AZURE_AD_CLIENT_ID', ''),
        'client_secret' => env('AZURE_AD_CLIENT_SECRET', ''),
        'redirect'      => env('AZURE_AD_CALLBACK_URL', '/login/microsoft/callback'),
    ],

    // The route to redirect the user to upon login.
    'redirect_on_login' => '/home',

    // The User Eloquent class.
    'user_class' => '\\App\\User',

    // How much time should be left before the access
    // token expires to attempt a refresh.
    'refresh_token_within' => 30,

    // The users table database column to store the user SSO ID.
    'user_id_field' => 'azure_id',

    // How to map azure user fields to Laravel user fields.
    // Do not include the id field above.
    // AzureUserField => LaravelUserField
    'user_map' => [
        'displayName'       => 'name',
        'mail'              => 'email',
        'userPrincipalName' => 'userPrincipalName',
    ],
];
