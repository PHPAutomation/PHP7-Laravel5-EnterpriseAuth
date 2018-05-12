<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    protected function findOrCreateUser($user)
    {
        $user_class = config('azure-oath.user_class');
        $authUser = $user_class::where(config('azure-oath.user_id_field'), $user->id)->first();

        if ($authUser) {
            return $authUser;
        }

        $UserFactory = new UserFactory();

        return $UserFactory->convertAzureUser($user);
    }

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

    public function certAuth()
    {
        // Make sure we got a client certificate from the web server
        if (! $_SERVER['SSL_CLIENT_CERT']) {
            throw new \Exception('TLS client certificate missing');
        }
        // try to parse the certificate we got
        $x509 = new \phpseclib\File\X509();
        // NGINX screws up the cert by putting a bunch of tab characters into it so we need to clean those out
        $asciicert = str_replace("\t", '', $_SERVER['SSL_CLIENT_CERT']);
        $cert = $x509->loadX509($asciicert);
        $names = $x509->getExtension('id-ce-subjectAltName');
        if (! $names) {
            throw new \Exception('TLS client cert missing subject alternative names');
        }
        // Search subject alt names for user principal name
        $upn = '';
        foreach ($names as $name) {
            foreach ($name as $key => $value) {
                if ($key == 'otherName') {
                    if (isset($value['type-id']) && $value['type-id'] == '1.3.6.1.4.1.311.20.2.3') {
                        $upn = $value['value']['utf8String'];
                    }
                }
            }
        }
        if (! $upn) {
            throw new \Exception('Could not find user principal name in TLS client cert');
        }
        $user_class = config('azure-oath.user_class');
        $user = $user_class::where('userPrincipalName', $upn)->first();
        if (! $user) {
            throw new \Exception('No user found with user principal name '.$upn);
        }
        //dd($user);
        return $user;
    }
}
