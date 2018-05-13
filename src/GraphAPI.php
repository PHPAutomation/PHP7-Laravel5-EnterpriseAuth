<?php

namespace Metrogistics\AzureSocialite;

class GraphAPI
{
    // Our AAD instance with all its useful information
    protected $azureActiveDirectory;
    protected $accessToken;
    protected $graphApiBaseUrl = 'https://graph.microsoft.com';
    protected $graphApiVersion = 'v1.0';

    public function __construct($azureActiveDirectory)
    {
        // IF they didnt pre-populate all our AAD stuff then make a new one for the common tenant
        if (! $azureActiveDirectory) {
            $azureActiveDirectory = new AzureActiveDirectory();
        }
        $this->azureActiveDirectory = $azureActiveDirectory;
    }

    // Users can set an access token or let the app default to its own
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    protected function getAccessToken()
    {
        if (! $this->accessToken) {
            $this->authenticateAsApplication();
        }

        return $this->accessToken;
    }

    protected function getGuzzleClientParameters()
    {
        $parameters = [
            'headers' => [
                'Authorization' => 'Bearer '.$this->getAccessToken(),
            ],
        ];

        return $parameters;
    }

    protected function authenticateAsApplication()
    {
        $guzzle = new \GuzzleHttp\Client();
        $url = $this->azureActiveDirectory->tokenEndpoint;
        $parameters = [
            'form_params' => [
                'scope'         => $this->graphApiBaseUrl.'/.default',
                'grant_type'    => 'client_credentials',
                'client_id'     => env('AZURE_AD_CLIENT_ID'),
                'client_secret' => env('AZURE_AD_CLIENT_SECRET'),
            ],
        ];
        $response = $guzzle->post($url, $parameters);
        $responseObject = json_decode($response->getBody());
        $this->setAccessToken($responseObject->access_token);
    }

    protected function getUrl($url)
    {
        $guzzle = new \GuzzleHttp\Client();
        $response = $guzzle->get($url, $this->getGuzzleClientParameters());
        $json = $response->getBody();
        $data = json_decode($json, true);

        return $data;
    }

    protected function buildUrl($pieces = [])
    {
        $url = $this->graphApiBaseUrl;
        // Include version before any pieces of the url
        array_unshift($pieces, $this->graphApiVersion);
        // Build the url
        foreach ($pieces as $piece) {
            $url .= '/'.$piece;
        }

        return $url;
    }

    public function getMe()
    {
        $pieces = ['me'];

        return $this->getUrl($this->buildUrl($pieces));
    }

    public function listUsers()
    {
        $pieces = ['users'];

        return $this->getUrl($this->buildUrl($pieces));
    }

    public function getUser($user)
    {
        $pieces = ['users', $user];

        return $this->getUrl($this->buildUrl($pieces));
    }

    public function getUserGroups($user)
    {
        $pieces = ['users', $user, 'memberOf'];

        return $this->getUrl($this->buildUrl($pieces));
    }

    public function listGroups()
    {
        $pieces = ['groups'];

        return $this->getUrl($this->buildUrl($pieces));
    }

    public function getGroup($group)
    {
        $pieces = ['groups', $group];

        return $this->getUrl($this->buildUrl($pieces));
    }

    // generalized function to parse out graph odata response values and index them by property
    public function parseGraphDataKeyByProperty($graphData, $key = 'value', $property = 'id')
    {
        $parsed = [];
        if (isset($graphData[$key]) && is_array($graphData[$key])) {
            foreach ($graphData[$key] as $value) {
                if (isset($value[$property]) && $value[$property]) {
                    $parsed[$value[$property]] = $value;
                }
            }
        }

        return $parsed;
    }

    // specific example to parse group list
    public function parseGroupListByName($graphData)
    {
        return $this->parseGraphDataKeyByProperty($graphData, 'value', 'displayName');
    }
}
