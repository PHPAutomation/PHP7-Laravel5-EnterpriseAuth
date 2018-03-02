<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/**
 * @SWG\Info(title="test oauth API", version="0.3")
 **/

/**
 * @SWG\Get(
 *     path="/api/hello",
 *     summary="Hello world test for API troubleshooting",
 *     @SWG\Response(response="200", description="Hello world example")
 * )
 **/

Route::middleware('auth:api')->get('/api/hello', function (Request $request) {
    return 'hello world';
});

// This was the default file contents of this file, it has been disabled by PHP7-Laravel5-EnterpriseAuth
/*
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/
