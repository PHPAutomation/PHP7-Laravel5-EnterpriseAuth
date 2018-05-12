<?php

// Authenticated user information routes
Route::middleware([config('azure-oath.apiroutes.middleware'), config('azure-oath.apiroutes.authmiddleware')])->group(function () {

    /**
     * @SWG\Get(
     *     path="/api/me",
     *     tags={"Me"},
     *     summary="Get user information about the token authenticated user",
     *     security={
     *         {"AzureAD": {}},
     *     },
     *     @SWG\Response(
     *         response=200,
     *         description="User information",
     *         ),
     *     @SWG\Response(
     *         response=401,
     *         description="Authentication failed",
     *         ),
     * )
     **/
    Route::get(config('azure-oath.apiroutes.myinfo'), 'Metrogistics\AzureSocialite\ApiAuthController@getAuthorizedUserInfo');

    /**
     * @SWG\Get(
     *     path="/api/me/roles",
     *     tags={"Me"},
     *     summary="Get user roles for the token authenticated user",
     *     security={
     *         {"AzureAD": {}},
     *     },
     *     @SWG\Response(
     *         response=200,
     *         description="User information",
     *         ),
     *     @SWG\Response(
     *         response=401,
     *         description="Authentication failed",
     *         ),
     * )
     **/
    Route::get(config('azure-oath.apiroutes.myroles'), 'Metrogistics\AzureSocialite\ApiAuthController@getAuthorizedUserRoles');

    /**
     * @SWG\Get(
     *     path="/api/me/roles/permissions",
     *     tags={"Me"},
     *     summary="Get user role permissions for the token authenticated user",
     *     security={
     *         {"AzureAD": {}},
     *     },
     *     @SWG\Response(
     *         response=200,
     *         description="User information",
     *         ),
     *     @SWG\Response(
     *         response=401,
     *         description="Authentication failed",
     *         ),
     * )
     **/
    Route::get(config('azure-oath.apiroutes.myrolespermissions'), 'Metrogistics\AzureSocialite\ApiAuthController@getAuthorizedUserRolesAbilities');
});
