<?php

Route::middleware([config('azure-oath.routes.middleware')])->group(function() {
    Route::get(config('azure-oath.routes.login'   ), 'Metrogistics\AzureSocialite\WebAuthController@redirectToOauthProvider');
    Route::get(config('azure-oath.routes.callback'), 'Metrogistics\AzureSocialite\WebAuthController@handleOauthResponse');

    // This handles a situation where a route with the NAME of login does not exist, we define it to keep from breaking framework redirects hard coded
    if (! \Route::has('login') ) {
        Route::get('login', 'Metrogistics\AzureSocialite\AuthController@loginOrRegister')->name('login');
    }
    if (! \Route::has('register') ) {
        Route::get('register', 'Metrogistics\AzureSocialite\AuthController@loginOrRegister')->name('register');
    }
});
