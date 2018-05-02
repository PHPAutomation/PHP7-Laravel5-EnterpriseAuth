<?php

    /**
     * @SWG\Info(title="test oauth API", version="0.3")
     **/

    // Redirect requests to /api to the swagger documentation
    //$api->any('', function (Illuminate\Http\Request $request) {
    $api->any('', function () {
        return redirect('api/documentation/');
    });

    /**
     * @SWG\Get(
     *     path="/api/hello",
     *     summary="Hello world test for API troubleshooting",
     *     @SWG\Response(response="200", description="Hello world example")
     * )
     **/
    $api->any('/api/hello', function () {
        return 'hello world';
    });
