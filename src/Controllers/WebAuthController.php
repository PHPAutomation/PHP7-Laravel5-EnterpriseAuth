<?php

namespace Metaclassing\EnterpriseAuth\Controllers;

use Illuminate\Routing\Controller;

class WebAuthController extends AuthController
{
    // Route to save unauthenticated users original page request and redirect to oauth provider redirect
    public function loginOrRegister(\Illuminate\Http\Request $request)
    {
        // This is what gets called after a user is redirected to /login by the framework
        $lastPage = $request->session()->get('url.intended');
        \Illuminate\Support\Facades\Log::info('AUTH loginOrRegister with request url '.$lastPage);

        // Make sure they are not going to end up in a redirect loop with the login route
        if ($lastPage && $lastPage != route('login')) {
            $request->session()->put('oauthIntendedUrl', $lastPage);
        }

        return redirect()->guest(config('enterpriseauth.routes.login'));
    }

    // Route to clear the session and redirect to oauth signout handler
    public function logout(\Illuminate\Http\Request $request)
    {
        auth()->logout();

        return redirect(config('enterpriseauth.routes.logout'));
    }

    // Route to redirect to oauth idp end-session endpoint
    public function logoutFromOauthProvider(\Illuminate\Http\Request $request)
    {
        $endSessionEndpoint = $this->azureActiveDirectory->endSessionEndpoint;

        return redirect($endSessionEndpoint);
    }

    // Route called to redirect administrative users to provide consent to access aad
    public function redirectToOauthAdminConsent(\Illuminate\Http\Request $request)
    {
        $url = $this->azureActiveDirectory->buildAdminConsentUrl(config('enterpriseauth.credentials.client_id'),
                                                                 config('enterpriseauth.credentials.callback_url'));
        //return new \Illuminate\Http\RedirectResponse($url);
        return redirect($url);
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
             .'?'
             .$this->buildAuthUrlQueryString();

        return $url;
    }

    // helper to build query string for oauth provider
    public function buildAuthUrlQueryString()
    {
        $fields = [
            'client_id'     => config('enterpriseauth.credentials.client_id'),
            'redirect_uri'  => config('enterpriseauth.credentials.callback_url'),
            'scope'         => 'https://graph.microsoft.com/.default',
            'response_type' => 'code',
        ];

        return http_build_query($fields);
    }

    // Route to handle response back from our oauth provider
    public function handleOauthResponse(\Illuminate\Http\Request $request)
    {
        // Handle user authentication responses
        if ($request->input('code')) {
            return $this->handleOauthLoginResponse($request);
        }
        if ($request->input('admin_consent')) {
            return 'Thank you';
        }
        throw new \Exception('Unhandled oauth response');
    }

    public function handleOauthLoginResponse(\Illuminate\Http\Request $request)
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
            $destination = config('enterpriseauth.redirect_on_login');
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
                'Accept' => 'application/json',
            ],
            'form_params' => [
                'code'          => $code,
                'scope'         => 'https://graph.microsoft.com/.default',
                'client_id'     => config('enterpriseauth.credentials.client_id'),
                'client_secret' => config('enterpriseauth.credentials.client_secret'),
                'redirect_uri'  => config('enterpriseauth.credentials.callback_url'),
                'grant_type'    => 'authorization_code',
             ],
        ];
        $response = $guzzle->post($url, $parameters);
        $responseObject = json_decode($response->getBody());
        $accessToken = $responseObject->access_token;

        return $accessToken;
    }
}
