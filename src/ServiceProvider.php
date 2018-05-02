<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Support\Facades\Auth;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Metrogistics\AzureSocialite\Middleware\Authenticate;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        // $this->app->bind('azure-user', function(){
        //     return new AzureUser(
        //         session('azure_user')
        //     );
        // });
    }

    public function boot()
    {
        // change the api auth guard to jwt rather than default of token
        //config(['auth.guards.api.driver' => 'jwt']);
        //dd(config('auth.guards.api'));

        // Actually I have my own oauth token cache based authentication guard now lol
        config(['auth.guards.api.driver' => 'oauthtoken']);
        Auth::extend('oauthtoken', function ($app, $name, array $config) {
            return new OauthTokenGuard(Auth::createUserProvider($config['provider']), $app->make('request'));
        });

        // Make sure that this vendor dir and the routes dir are in any scanned paths for swagger documentation
        $swaggerScanPaths = config('l5-swagger.paths.annotations');
        if (! is_array($swaggerScanPaths)) {
            $swaggerScanPaths = [$swaggerScanPaths];
        }
        if (! in_array(base_path('routes'), $swaggerScanPaths)) {
            $swaggerScanPaths[] = base_path('routes');
        }
        if (! in_array(__DIR__.'/../routes/', $swaggerScanPaths)) {
            $swaggerScanPaths[] = __DIR__.'/../routes/';
        }
        config(['l5-swagger.paths.annotations' => $swaggerScanPaths]);

        $this->publishes([
            __DIR__.'/../publish/config/azure-oath.php'                                                    => config_path('azure-oath.php'),
            __DIR__.'/../publish/database/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php' => $this->app->databasePath().'/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php',
            __DIR__.'/../publish/app/User.php'                                                             => app_path().'/User.php',
            __DIR__.'/../publish/routes/api.php'                                                           => base_path('routes').'/api.php',
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/../publish/config/azure-oath.php', 'azure-oath'
        );

        $this->app['Laravel\Socialite\Contracts\Factory']->extend('azure-oauth', function ($app) {
            return $app['Laravel\Socialite\Contracts\Factory']->buildProvider(
                'Metrogistics\AzureSocialite\AzureOauthProvider',
                config('azure-oath.credentials')
            );
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/api.microsoft.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.microsoft.php');

        // If the routes files for the swagger oauth config is NOT present, and we have all the right info, then generate it really quick
        $swaggerAzureadFile = __DIR__.'/../routes/swagger.azuread.php';
        if (! file_exists($swaggerAzureadFile) && env('AZURE_AD_CLIENT_ID') && env('AZURE_AD_OPENID_URL')) {
            $openidConfig = $this->getOpenidConfiguration(env('AZURE_AD_OPENID_URL'));
            $authorizationUrl = $openidConfig['authorization_endpoint'];
            if (! $authorizationUrl) {
                throw new \Exception('Error building swagger oauth config, azure ad openid url didnt give me an authorization url!');
            }
            $client_id = env('AZURE_AD_CLIENT_ID');
            $contents = <<<EOF
<?php
/**
 * @SWG\SecurityScheme(
 *   securityDefinition="AzureAD",
 *   type="oauth2",
 *   authorizationUrl="$authorizationUrl?resource=https://graph.microsoft.com",
 *   flow="implicit",
 *   scopes={
 *       "openid": "Use client_id: $client_id"
 *   }
 * )
 **/
EOF;
            file_put_contents($swaggerAzureadFile, $contents);
        }
    }

    public function getOpenidConfiguration($url)
    {
        $guzzle = new \GuzzleHttp\Client();

        $response = $guzzle->get($url);

        return json_decode($response->getBody(), true);
    }
}
