<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class ApiAuthController extends AuthController
{

    public function handleApiOauthLogin(\Illuminate\Http\Request $request)
    {
        $token = $request->input('access_token');
        if (!$token) {
            throw new \Exception('error: access_token is missing');
        }

        $user = $this->mapUserToObject($this->getUserByToken($token));
        $user->groups = $this->groups($token);
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

        try {
            // verify the credentials and create a token for the user
            if (! $token = \JWTAuth::fromUser($authUser)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
        } catch (JWTException $e) {
            // something went wrong
            return response()->json(['error' => 'could_not_create_token'], 500);
        }
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
