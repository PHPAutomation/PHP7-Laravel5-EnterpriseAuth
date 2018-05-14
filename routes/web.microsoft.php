<?php

Route::middleware([config('enterpriseauth.routes.middleware')])->group(function () {
    Route::get(config('enterpriseauth.routes.login'), 'Metaclassing\EnterpriseAuth\Controllers\WebAuthController@redirectToOauthProvider');
    Route::get(config('enterpriseauth.routes.callback'), 'Metaclassing\EnterpriseAuth\Controllers\WebAuthController@handleOauthResponse');

    // This handles a situation where a route with the NAME of login does not exist, we define it to keep from breaking framework redirects hard coded
    if (! \Route::has('login')) {
        Route::get('login', 'Metaclassing\EnterpriseAuth\Controllers\WebAuthController@loginOrRegister')->name('login');
    }
    if (! \Route::has('register')) {
        Route::get('register', 'Metaclassing\EnterpriseAuth\Controllers\WebAuthController@loginOrRegister')->name('register');
    }
});
