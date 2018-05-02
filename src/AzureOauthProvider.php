<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\User;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;

class AzureOauthProvider extends AbstractProvider implements ProviderInterface
{
    const IDENTIFIER = 'AZURE_OAUTH';
    protected $scopes = ['User.Read'];
    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://login.microsoftonline.com/common/oauth2/authorize', $state);
    }

    protected function getTokenUrl()
    {
        return 'https://login.microsoftonline.com/common/oauth2/token';
    }

    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
            'resource'   => 'https://graph.microsoft.com',
        ]);
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/me/', [
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function getUserGroupsByToken($token)
    {
        $response = $this->getHttpClient()->get('https://graph.microsoft.com/v1.0/me/memberOf/', [
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

    public function user()
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $response = $this->getAccessTokenResponse($this->getCode());

        $token = Arr::get($response, 'access_token');
        $user = $this->mapUserToObject($this->getUserByToken($token));
        $user->groups = $this->groups($token);
        $user->idToken = Arr::get($response, 'id_token');
        $user->expiresAt = time() + Arr::get($response, 'expires_in');

        return $user->setToken($token)
                    ->setRefreshToken(Arr::get($response, 'refresh_token'));
    }

    protected function mapUserToObject(array $user)
    {
        //dd($user);
        // WELL this is extremely stupid. if email address isnt set, use their UPN...
        if (! $user['mail']) {
            $user['mail'] = $user['userPrincipalName'];
        }

        return (new User())->setRaw($user)->map([
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
}
