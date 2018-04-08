<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class ApiAuthController extends AuthController
{

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
        if ($header && preg_match($regex, $header, $matches) ) {
            $oauthAccessToken = $matches[1];
        }

        return $oauthAccessToken;
    }

    public function validateOauthCreateOrUpdateUserAndGroups($oauthAccessToken)
    {
        $user = $this->mapUserToObject($this->getUserByToken($oauthAccessToken));
        $user->groups = $this->groups($oauthAccessToken);
        //dd($user);

        $authUser = $this->findOrCreateUser($user);

        // If we have user group information from this oauth attempt
        if(count($user->groups)) {
            // remove the users existing database roles before assigning new ones
            $oldroles = $authUser->roles()->get();
            foreach ($oldroles as $role) {
                $authUser->retract($role);
            }
            // add the user to each group they are assigned
            $newroles = $user->groups;
            foreach ($newroles as $role) {
                $authUser->assign($role);
            }
        }

        // Cache the users oauth accss token mapped to their user object for stuff and things
        $key = '/oauth/tokens/'.$oauthAccessToken;
        //\Cache::forever($key, $authUser);
        \Cache::put($key, $authUser, 1440);

        return $authUser;
    }

    public function handleApiOauthLogin(\Illuminate\Http\Request $request)
    {
        $oauthAccessToken = $this->extractOauthAccessTokenFromRequest($request);

        // If we cant find ANY token to use, abort.
        if (!$oauthAccessToken) {
            throw new \Exception('error: token/access_token/authorization bearer token is missing');
        }

        // Try to authenticate their token, update groups, cache results, etc.
        $authUser = $this->validateOauthCreateOrUpdateUserAndGroups($oauthAccessToken);

        try {
            // verify the credentials and create a token for the user
            if (! $token = \JWTAuth::fromUser($authUser)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }

        // Cache the users oauth accss token mapped to their user object for stuff and things
        $key = '/oauth/tokens/'.$token;
        //\Cache::forever($key, $authUser);
        \Cache::put($key, $authUser, 1440);

        // if no errors are encountered we can return a JWT
        return response()->json(compact('token'));
    }

    public function getUserByToken($token)
    {
        $guzzle = new \GuzzleHttp\Client();

        $response = $guzzle->get('https://graph.microsoft.com/v1.0/me/', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function getUserGroupsByToken($token)
    {
        $guzzle = new \GuzzleHttp\Client();

        $response = $guzzle->get('https://graph.microsoft.com/v1.0/me/memberOf/', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);
        return json_decode($response->getBody(), true);
    }

    protected function groups($token)
    {
        $groups = [];
        try {
        // try updating users bouncer permissions
            $azureadgroups = $this->getUserGroupsByToken($token);
            // only proceed if we got a good response with group info
            if (isset($azureadgroups['value']) && count($azureadgroups['value'])) {
                foreach($azureadgroups['value'] as $group) {
                    $groups[] = $group['displayName'];
                }
            }
        } catch (\GuzzleHttp\Exception\ClientException  $e) {
            // This is usually due to insufficient permissions on the azure ad app
            throw new \Exception('This AzureAD application does not seem to have permission to view user groups. Did you configure that permission in AzureAD correctly? '.$e->getMessage());
        } catch (\Exception $e) {
            // I have no idea what would cause other exceptions yet
            throw $e;
        }
        return $groups;
    }

    protected function mapUserToObject(array $user)
    {
        if (!$user['mail']) {
            $user['mail'] = $user['userPrincipalName'];
        }
        return (new \Laravel\Socialite\Two\User())->setRaw($user)->map([
            'id'                => $user['id'],
            'name'              => $user['displayName'],
            'email'             => $user['mail'],
            'password'          => '',

            'businessPhones'    => $user['businessPhones'],
            'displayName'       => $user['displayName'],
            'givenName'         => $user['givenName'],
            'jobTitle'          => $user['jobTitle'],
            'mail'              => $user['mail'],
            'mobilePhone'       => $user['mobilePhone'],
            'officeLocation'    => $user['officeLocation'],
            'preferredLanguage' => $user['preferredLanguage'],
            'surname'           => $user['surname'],
            'userPrincipalName' => $user['userPrincipalName'],
        ]);
    }

    public function getAuthorizedUserInfo(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        return response()->json($user);
    }

    public function getAuthorizedUserRoles(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $roles = $user->roles()->get();
        return response()->json($roles);
    }

    public function getAuthorizedUserRolesAbilities(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $roles = $user->roles()->get()->all();
        foreach($roles as $key => $role) {
            $role->permissions = $role->abilities()->get()->all();
            if(!count($role->permissions)) {
                unset($roles[$key]);
            }
        }
        $roles = array_values($roles);
        return response()->json($roles);
    }

}
