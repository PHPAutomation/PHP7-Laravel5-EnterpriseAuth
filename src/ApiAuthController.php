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
        if ($header && preg_match($regex, $header, $matches)) {
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
        if (count($user->groups)) {
            // remove the users existing database roles before assigning new ones
            \DB::table('assigned_roles')
               ->where('entity_id', $authUser->id)
               ->where('entity_type', get_class($authUser))
               ->delete();
            // add the user to each group they are assigned
            $authUser->assign($user->groups);
        }

        // Cache the users oauth accss token mapped to their user object for stuff and things
        $key = '/oauth/tokens/'.$oauthAccessToken;
        // TODO: Replace static value 1440 with actual life of the oauth access token we got
        \Cache::put($key, $authUser, 1440);

        return $authUser;
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
                foreach ($azureadgroups['value'] as $group) {
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
        if (! $user['mail']) {
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
