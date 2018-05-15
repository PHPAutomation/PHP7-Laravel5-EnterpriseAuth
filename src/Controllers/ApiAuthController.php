<?php

namespace Metaclassing\EnterpriseAuth\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

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
        // Check the cache to see if this is a previously authenticated oauth access token
        $key = '/oauth/tokens/'.$accessToken;
        if ($accessToken && \Cache::has($key)) {
            $user = \Cache::get($key);
        // Check to see if they have newly authenticated with an oauth access token
        } else {
            try {
                $user = $this->validateOauthCreateOrUpdateUserAndGroups($accessToken);
            } catch (\Exception $e) {
                //echo 'token auth error: '.$e->getMessage();
            }
        }

        return $user;
    }

    public function attemptCertAuth()
    {
        try {
            return $apiAuthController->certAuth();
        } catch (\Exception $e) {
            //echo 'cert auth error: '.$e->getMessage();
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
