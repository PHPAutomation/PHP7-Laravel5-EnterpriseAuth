<?php

// Authenticated user information routes
Route::middleware([config('enterpriseauth.apiroutes.middleware'), config('enterpriseauth.apiroutes.authmiddleware')])->group(function () {

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
    Route::get(config('enterpriseauth.apiroutes.myinfo'), 'Metaclassing\EnterpriseAuth\Controllers\ApiAuthController@getAuthorizedUserInfo');

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
    Route::get(config('enterpriseauth.apiroutes.myroles'), 'Metaclassing\EnterpriseAuth\Controllers\ApiAuthController@getAuthorizedUserRoles');

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
    Route::get(config('enterpriseauth.apiroutes.myrolespermissions'), 'Metaclassing\EnterpriseAuth\Controllers\ApiAuthController@getAuthorizedUserRolesAbilities');
});
