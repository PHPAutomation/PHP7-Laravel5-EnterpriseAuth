<?php

namespace Metrogistics\AzureSocialite;

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
        $this->openIdConfigUrl = $this->baseUrl . '/'
                               . $this->tenantName . '/'
                               . $this->version . '/'
                               . $this->wellKnownOpenIdConfig;
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
        $this->downloadOpenIdConfig();
        $this->authorizationEndpoint = $this->openIdConfig['authorization_endpoint'];
        $this->tokenEndpoint = $this->openIdConfig['token_endpoint'];
        $this->endSessionEndpoint = $this->openIdConfig['end_session_endpoint'];
    }

}
