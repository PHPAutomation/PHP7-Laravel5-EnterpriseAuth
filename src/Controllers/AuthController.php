<?php

namespace Metaclassing\EnterpriseAuth\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    protected $azureActiveDirectory;

    public function __construct()
    {
        $tenant = config('enterpriseauth.credentials.tenant');
        $this->azureActiveDirectory = new \Metaclassing\EnterpriseAuth\AzureActiveDirectory($tenant);
    }

    // This is called after a web auth gets an access token, or api auth sends an access token
    public function validateOauthCreateOrUpdateUserAndGroups($accessToken)
    {
        $userData = $this->getMicrosoftGraphSelf($accessToken);
        $userData = $this->scrubMicrosoftGraphUserData($userData);

        // This is a laravel \App\User
        $user = $this->findOrCreateUser($userData);

        // Try to update the group/role membership for this user
        $this->updateGroups($user);

        // Cache the users oauth accss token mapped to their user object for stuff and things
        $key = '/oauth/tokens/'.$accessToken;
        // TODO: Replace static value 1440 with actual life of the oauth access token we got
        \Cache::put($key, $user, 1440);

        return $user;
    }

    public function getMicrosoftGraphSelf($accessToken)
    {
        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($accessToken);
        $user = $graph->createRequest('GET', '/me')
                      ->setReturnType(\Microsoft\Graph\Model\User::class)
                      ->execute();

        return $user->jsonSerialize();
    }

    public function scrubMicrosoftGraphUserData($userData)
    {
        // Fix any stupid crap with missing or null fields
        if (! isset($userData['mail']) || ! $userData['mail']) {
            $userData['mail'] = $userData['userPrincipalName'];
        }

        return $userData;
    }

    protected function findOrCreateUser($userData)
    {
        // Configurable \App\User type and ID field name
        $userType = config('enterpriseauth.user_class');
        $userIdField = config('enterpriseauth.user_id_field');
        // Try to find an existing user
        $user = $userType::where($userIdField, $userData['id'])->first();
        // If we dont have an existing user
        if (! $user) {
            // Go create a new one with this data
            $user = $this->createUserFromAzureData($userData);
        }

        return $user;
    }

    // This takes the azure userdata and makes a new user out of it
    public function createUserFromAzureData($userData)
    {
        // Config options for user type/id/field map
        $userType = config('enterpriseauth.user_class');
        $userFieldMap = config('enterpriseauth.user_map');
        $idField = config('enterpriseauth.user_id_field');

        // Should build new \App\User
        $user = new $userType();
        $user->$idField = $userData['id'];
        // Go through any other fields the config wants us to map
        foreach ($userFieldMap as $azureField => $userField) {
            $user->$userField = $userData[$azureField];
        }
        // Save our newly minted user
        $user->save();

        return $user;
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
        $x509->loadX509($asciicert);
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
        $user_class = config('enterpriseauth.user_class');
        $user = $user_class::where('userPrincipalName', $upn)->first();
        if (! $user) {
            throw new \Exception('No user found with user principal name '.$upn);
        }
        //dd($user);
        return $user;
    }

    public function updateGroups($user)
    {
        // See if we can get the users group membership data
        $groupData = $this->getMicrosoftGraphGroupMembership($user);

        // Process group data into a list of displayNames we use as roles
        $groups = [];
        foreach ($groupData as $info) {
            $groups[] = $info['displayName'];
        }

        // If we have user group information from this oauth attempt
        if (count($groups)) {
            // remove the users existing database roles before assigning new ones
            \DB::table('assigned_roles')
               ->where('entity_id', $user->id)
               ->where('entity_type', get_class($user))
               ->delete();
            // add the user to each group they are assigned
            $user->assign($groups);
        }
    }

    public function getMicrosoftGraphGroupMembership($user)
    {
        // Get an access token for our application (not the users token)
        $accessToken = $this->azureActiveDirectory->getApplicationAccessToken(config('enterpriseauth.credentials.client_id'), config('enterpriseauth.credentials.client_secret'));

        // Use the app access token to get a given users group membership
        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($accessToken);
        $path = '/users/'.$user->userPrincipalName.'/memberOf';
        $groups = $graph->createRequest('GET', $path)
                        ->setReturnType(\Microsoft\Graph\Model\Group::class)
                        ->execute();

        // Convert the microsoft graph group objects into data that is useful
        $groupData = [];
        foreach ($groups as $group) {
            $groupData[] = $group->jsonSerialize();
        }

        return $groupData;
    }
}