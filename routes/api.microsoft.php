<?php

// Unauthenticated API routes
Route::middleware([config('azure-oath.apiroutes.middleware')])->group(function() {
    /**
     * @SWG\Get(
     *     path="/api/login/microsoft",
     *     tags={"Authentication"},
     *     summary="Get a JWT (JSON web token) by sending an Azure AD Oauth access_token",
     *     @SWG\Parameter(
     *         name="access_token",
     *         in="formData",
     *         description="Azure AD Oauth Access Token",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Authentication succeeded",
     *         ),
     *     ),
     * )
     **/
    Route::get(config('azure-oath.apiroutes.login'), 'Metrogistics\AzureSocialite\ApiAuthController@handleOauthLogin');

    /**
     * @SWG\Post(
     *     path="/api/login/microsoft",
     *     tags={"Authentication"},
     *     summary="Get a JWT (JSON web token) by sending an Azure AD Oauth access_token",
     *     @SWG\Parameter(
     *         name="access_token",
     *         in="formData",
     *         description="Azure AD Oauth Access Token",
     *         required=true,
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response=200,
     *         description="Authentication succeeded",
     *         ),
     *     ),
     * )
     **/
    Route::post(config('azure-oath.apiroutes.login'), 'Metrogistics\AzureSocialite\ApiAuthController@handleApiOauthLogin');
});

// Authenticated user information routes
// TODO: Docblock these for the swagger documentation
Route::middleware([config('azure-oath.apiroutes.middleware'), config('azure-oath.apiroutes.authmiddleware')])->group(function() {
    Route::get(config('azure-oath.apiroutes.myinfo'), 'Metrogistics\AzureSocialite\ApiAuthController@getAuthorizedUserInfo');
    Route::get(config('azure-oath.apiroutes.myroles'), 'Metrogistics\AzureSocialite\ApiAuthController@getAuthorizedUserRoles');
    Route::get(config('azure-oath.apiroutes.myrolespermissions'), 'Metrogistics\AzureSocialite\ApiAuthController@getAuthorizedUserRolesAbilities');
});
