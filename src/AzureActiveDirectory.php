<?php

namespace Metaclassing\EnterpriseAuth;

class AzureActiveDirectory
{
    // Tenant name something.onmicrosoft.com
    public $tenantName = '';
    // Azure AD base url to use
    public $baseUrl = 'https://login.microsoftonline.com';
    // Azure AD version
    public $version = 'v2.0';
    // .well-known/openid-config
    public $wellKnownOpenIdConfig = '.well-known/openid-configuration';
    // URL to download the latest openid config
    public $openIdConfigUrl = '';
    // Contents of the openid config assoc array parsed from json
    public $openIdConfig = [];
    // AAD authorization endpoint
    public $authorizationEndpoint = '';
    // AAD token endpoint
    public $tokenEndpoint = '';
    // AAD logout endpoint
    public $endSessionEndpoint = '';

    public function __construct($tenantName = 'common')
    {
        $this->setTenantName($tenantName);
        $this->parseOpenIdConfig();
    }

    public function setTenantName($tenantName)
    {
        // IF we are not using the common tenant
        if ($tenantName != 'common') {
            // Make sure the tenant is formatted like xyzcorp.onmicrosoft.com
            $regex = '/\.onmicrosoft\.com/';
            if (! preg_match($regex, $tenantName, $hits)) {
                // Append the suffix if it is missing
                $tenantName .= '.onmicrosoft.com';
            }
        }
        $this->tenantName = $tenantName;
    }

    public function buildOpenIdConfigUrl()
    {
        $this->openIdConfigUrl = $this->baseUrl.'/'
                               . $this->tenantName.'/'
                               . $this->version.'/'
                               . $this->wellKnownOpenIdConfig;
    }

    public function buildAdminConsentUrl($clientId, $redirectUri)
    {
        $url = $this->baseUrl.'/'
             . $this->tenantName.'/'
             . 'adminconsent'
             . '?client_id='.$clientId
             . '&redirect_uri='.$redirectUri;

        return $url;
    }

    public function downloadOpenIdConfig()
    {
        $this->buildOpenIdConfigUrl();
        $guzzle = new \GuzzleHttp\Client();
        $response = $guzzle->get($this->openIdConfigUrl);
        $json = $response->getBody();
        $this->openIdConfig = json_decode($json, true);
    }

    public function parseOpenIdConfig()
    {
        $this->checkCachedOpenIdConfig();
        $this->authorizationEndpoint = $this->openIdConfig['authorization_endpoint'];
        $this->tokenEndpoint = $this->openIdConfig['token_endpoint'];
        $this->endSessionEndpoint = $this->openIdConfig['end_session_endpoint'];
    }

    public function checkCachedOpenIdConfig()
    {
        // See if we already have this tenants aad config cached
        $key = '/azureactivedirectory/'.$this->tenantName.'/config';
        if (\Cache::has($key)) {
            // Use the cached version if available
            $this->openIdConfig = \Cache::get($key);
        } else {
            // Download it if we dont have it
            $this->downloadOpenIdConfig();
            // Keep it around for 60 minutes
            \Cache::put($key, $this->openIdConfig, 60);
        }
    }

    public function getApplicationAccessToken($clientId, $clientSecret, $scopes = ['https://graph.microsoft.com/.default'])
    {
        $scope = implode(' ', $scopes);
        $guzzle = new \GuzzleHttp\Client();
        $url = $this->tokenEndpoint;
        $parameters = [
            'form_params' => [
                'scope'         => $scope,
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
            ],
        ];
        $response = $guzzle->post($url, $parameters);
        $responseObject = json_decode($response->getBody());

        return $responseObject->access_token;
    }
}
