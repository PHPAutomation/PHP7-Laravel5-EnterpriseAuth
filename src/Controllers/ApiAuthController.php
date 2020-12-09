<?php

namespace Metaclassing\EnterpriseAuth\Controllers;

use Illuminate\Routing\Controller;

class ApiAuthController extends AuthController
{
    public function authenticateRequest(\Illuminate\Http\Request $request)
    {
        $accessToken = $this->extractOauthAccessTokenFromRequest($request);

        // IF we got a token, prefer using that over cert auth
        if ($accessToken) {
            return $this->attemptTokenAuth($accessToken);
        } else {
            return $this->attemptCertAuth();
        }
    }

    public function attemptTokenAuth($accessToken)
    {
        $user = null;

        // Check the cache to see if this is a previously authenticated oauth access token
        $key = '/oauth/tokens/'.$accessToken;
        if ($accessToken && \Cache::has($key)) {
            $user = \Cache::get($key);
        // Check to see if they have newly authenticated with an oauth access token
        } else {
            try {
                $user = $this->identifyAndValidateAccessToken($accessToken);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::info('api auth token exception: '.$e->getMessage());
            }
        }

        return $user;
    }

    // This checks the kind of token and authenticates it appropriately
    public function identifyAndValidateAccessToken($accessToken)
    {
        // parse the token into readable info
        $token = $this->unpackJwt($accessToken);
        // identify the type of token
        $type = $this->identifyToken($token);
        // handle different types of tokens
        \Illuminate\Support\Facades\Log::debug('api auth identified token type '.$type);
        switch ($type) {
            case 'azureapp':
                $user = $this->validateOauthCreateOrUpdateAzureApp($accessToken);
                break;
            case 'azureuser':
                $user = $this->validateOauthCreateOrUpdateAzureUser($accessToken);
                break;
            case 'graphuser':
                $user = $this->validateOauthCreateOrUpdateUserAndGroups($accessToken);
                break;
            default:
                throw new \Exception('Could not identify type of access token: '.json_encode($token));
        }

        return $user;
    }

    // figure out wtf kind of token we are being given
    public function identifyToken($token)
    {
        // start with an unidentified token type
        $type = 'unknown';

        // If the token is for the graph API
        if (isset($token['payload']['aud']) && $token['payload']['aud'] == 'https://graph.microsoft.com') {
            // and its a user
            if (isset($token['payload']['name']) && isset($token['payload']['upn'])) {
                $type = 'graphuser';
            } else {
                // This is entirely possible but I have no idea how to decode or validate it...
            }
            // If the token uses OUR app id as the AUDience then we dont need the graph api
        } elseif (isset($token['payload']['aud']) && $token['payload']['aud'] == config('enterpriseauth.credentials.client_id')) {
            // users have names
            if (isset($token['payload']['name']) && isset($token['payload']['preferred_username'])) {
                $type = 'azureuser';
            // apps do not?
            } else {
                $type = 'azureapp';
            }
        }

        return $type;
    }

    // This is called after an api auth gets intercepted and determined to be an app access token
    public function validateOauthCreateOrUpdateAzureApp($accessToken)
    {
        // Perform the validation and get the payload
        $appData = $this->validateRSAToken($accessToken);
        // Determine what property to use or explode
        $prop = '';
        if (property_exists($appData, 'oid')) {
            $prop = 'oid';
        } elseif (property_exists($appData, 'azp')) {
            $prop = 'azp';
        } else {
            throw new \Exception('Token data was valid but did not contain a known property to use for subject/caller');
        }
        // Find or create for azure app user object
        $userData = [
            'id'                => $appData->$prop,
            'displayName'       => $appData->$prop,
            'mail'              => $appData->$prop,
        ];

        // This is a laravel \App\User
        $user = $this->findOrCreateUser($userData);
        \Illuminate\Support\Facades\Log::debug('oauth authentication for application '.$user->name);

        // Cache the users oauth accss token mapped to their user object for stuff and things
        $key = '/oauth/tokens/'.$accessToken;
        $remaining = $this->getTokenMinutesRemaining($accessToken);
        \Illuminate\Support\Facades\Log::debug('api auth token cached for '.$remaining.' minutes');
        // Cache the token until it expires
        \Cache::put($key, $user, $remaining);

        return $user;
    }

    // This is called after an api auth gets intercepted and determined to be an app access token
    public function validateOauthCreateOrUpdateAzureUser($accessToken)
    {
        // Perform the validation and get the payload
        $appData = $this->validateRSAToken($accessToken);
        // Find or create for azure app user object
        $userData = [
            'id'                => $appData->oid,
            'displayName'       => $appData->name,
            'mail'              => $appData->preferred_username,
            'userPrincipalName' => $appData->preferred_username,
        ];

        // This is a laravel \App\User
        $user = $this->findOrCreateUser($userData);

        // Try to update the group/role membership for this user
        $this->updateGroups($user);

        // Cache the users oauth accss token mapped to their user object for stuff and things
        $key = '/oauth/tokens/'.$accessToken;
        $remaining = $this->getTokenMinutesRemaining($accessToken);
        \Illuminate\Support\Facades\Log::debug('api auth token cached for '.$remaining.' minutes');
        // Cache the token until it expires
        \Cache::put($key, $user, $remaining);

        return $user;
    }

    // this checks the app token, validates it, returns decoded signed data
    public function validateRSAToken($accessToken)
    {
        // Unpack our jwt to verify it is correctly formed
        $token = $this->unpackJwt($accessToken);
        // app tokens must be signed in RSA
        if (! isset($token['header']['alg']) || $token['header']['alg'] != 'RS256') {
            throw new \Exception('Token is not using the correct signing algorithm RS256 '.$accessToken);
        }
        // app tokens are RSA signed with a key ID in the header of the token
        if (! isset($token['header']['kid'])) {
            throw new \Exception('Token with unknown RSA key id can not be validated '.$accessToken);
        }
        // Make sure the key id is known to our azure ad information
        $kid = $token['header']['kid'];
        if (! isset($this->azureActiveDirectory->signingKeys[$kid])) {
            throw new \Exception('Token signed with unknown KID '.$kid);
        }
        // get the x509 encoded cert body
        $x5c = $this->azureActiveDirectory->signingKeys[$kid]['x5c'];
        // if this is an array use the first entry
        if (is_array($x5c)) {
            $x5c = reset($x5c);
        }
        // Get the X509 certificate for the selected key id
        $certificate = '-----BEGIN CERTIFICATE-----'.PHP_EOL
                     .$x5c.PHP_EOL
                     .'-----END CERTIFICATE-----';
        // Perform the verification and get the verified payload results
        $payload = \Firebase\JWT\JWT::decode($accessToken, $certificate, ['RS256']);

        return $payload;
    }

    public function attemptCertAuth()
    {
        try {
            return $this->certAuth();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::info('api auth cert exception: '.$e->getMessage());
        }
    }

    // Helper to find a token wherever it is hidden and attempt to auth it
    public function extractOauthAccessTokenFromRequest(\Illuminate\Http\Request $request)
    {
        $oauthAccessToken = '';

        // IF we get an explicit TOKEN=abc123 in the $request
        if ($request->query('token')) {
            $oauthAccessToken = $request->query('token');
        }

        // IF posted as access_token=abc123 in the $request
        if ($request->input('access_token')) {
            $oauthAccessToken = $request->input('access_token');
        }

        // IF the request has an Authorization: Bearer abc123 header
        $header = $request->headers->get('authorization');
        $regex = '/bearer\s+(\S+)/i';
        if ($header && preg_match($regex, $header, $matches)) {
            if (! isset($matches[1])) {
                throw new \Exception('Error parsing bearer token in header: '.$header);
            }
            $oauthAccessToken = $matches[1];
        }

        return $oauthAccessToken;
    }

    // Route to dump out the authenticated API user
    public function getAuthorizedUserInfo(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();

        return response()->json($user);
    }

    // Route to dump out the authenticated users groups/roles
    public function getAuthorizedUserRoles(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $roles = $user->roles()->get();

        return response()->json($roles);
    }

    // Route to dump out the authenticated users group/roles abilities/permissions
    public function getAuthorizedUserRolesAbilities(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $roles = $user->roles()->get()->all();
        foreach ($roles as $key => $role) {
            $role->permissions = $role->abilities()->get()->all();
            if (! count($role->permissions)) {
                unset($roles[$key]);
            }
        }
        $roles = array_values($roles);

        return response()->json($roles);
    }
}
