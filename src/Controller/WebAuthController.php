<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class WebAuthController extends AuthController
{
    // Route to save unauthenticated users original page request and redirect to oauth provider redirect
    public function loginOrRegister(\Illuminate\Http\Request $request)
    {
        // This detects if we should hit the API auth handler or WEB auth handler
        if ($request->expectsJson()) {
            $response = response()->json(['message' => $exception->getMessage()], 401);
        } else {
            // This is what gets called after a user is redirected to /login by the framework
            $lastPage = $request->session()->get('url.intended');
            \Illuminate\Support\Facades\Log::info('AUTH loginOrRegister with request url '.$lastPage);
            // Make sure they are not going to end up in a redirect loop with the login route
            if ($lastPage && $lastPage != route('login')) {
                $request->session()->put('oauthIntendedUrl', $lastPage);
            }
            $response = redirect()->guest(config('azure-oath.routes.login'));
        }

        return $response;
    }

    // Route called to redirect unauthenticated users to oauth identity provider
    public function redirectToOauthProvider(\Illuminate\Http\Request $request)
    {
        $url = $this->buildAuthUrl();
        //return new \Illuminate\Http\RedirectResponse($url);
        return redirect($url);
    }

    // Helper to build redirect url from azure AD tenant
    public function buildAuthUrl()
    {
        $url = $this->azureActiveDirectory->authorizationEndpoint
             . '?'
             . $this->buildAuthUrlQueryString();

        return $url;
    }

    // helper to build query string for oauth provider
    public function buildAuthUrlQueryString()
    {
        $fields = [
            'client_id'     => ENV('AZURE_AD_CLIENT_ID'),
            'redirect_uri'  => ENV('AZURE_AD_CALLBACK_URL'),
            'scope'         => 'https://graph.microsoft.com/.default',
            'response_type' => 'code',
        ];

        return http_build_query($fields);
    }

    // Route to handle response back from our oauth provider
    public function handleOauthResponse(\Illuminate\Http\Request $request)
    {
        // Turn coke into pepsi
        $accessToken = $this->getAccessTokenFromCode($request->input('code'));
        // Get the associated laravel \App\User object
        $user = $this->validateOauthCreateOrUpdateUserAndGroups($accessToken);
        // Authenticate the users session
        auth()->login($user, true);

        // Check to see if there is an intended destination url saved
        $destination = $request->session()
                               ->get('oauthIntendedUrl');
        // If there is no intended destination url, use the default
        if (! $destination) {
            $destination = config('azure-oath.redirect_on_login');
        }
        \Illuminate\Support\Facades\Log::info('AUTH success USER ID '.$user->id.' with redirect url '.$destination);

        return redirect($destination);
    }

    // Turn coke into pepsi: Take the authorization code and turn it into an access token for graph api
    public function getAccessTokenFromCode($code)
    {
        $guzzle = new \GuzzleHttp\Client();
        $url = $this->azureActiveDirectory->tokenEndpoint;
        $parameters = [
            'headers' => [
                'Accept' => 'application/json'
            ],
            'form_params' => [
                'code'          => $code,
                'scope'         => 'https://graph.microsoft.com/.default',
                'client_id'     => env('AZURE_AD_CLIENT_ID'),
                'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
                'redirect_uri'  => ENV('AZURE_AD_CALLBACK_URL'),
                'grant_type'    => 'authorization_code',
             ]
        ];
        $response = $guzzle->post($url, $parameters);
        $responseObject = json_decode($response->getBody());
        $accessToken = $responseObject->access_token;
        return $accessToken;
    }
}
