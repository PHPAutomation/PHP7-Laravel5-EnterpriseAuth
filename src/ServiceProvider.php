<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        // Make sure nobody is including or running this thing without all the required env settings
        $requiredVariables = ['AZURE_AD_CLIENT_ID', 'AZURE_AD_CLIENT_SECRET', 'AZURE_AD_TENANT', 'AZURE_AD_CALLBACK_URL'];
        foreach($requiredVariables as $env) {
            if (! env($env)) {
                throw new \Exception('enterpriseauth setup error: missing mandatory .env value for '.$env);
            }
        }

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

        // Make sure the publish command picks up our config, migration, user model, and dummy API route files
        $this->publishes([
            __DIR__.'/../publish/config/azure-oath.php'                                                    => config_path('azure-oath.php'),
            __DIR__.'/../publish/database/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php' => $this->app->databasePath().'/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php',
            __DIR__.'/../publish/app/User.php'                                                             => app_path().'/User.php',
            __DIR__.'/../publish/routes/api.php'                                                           => base_path('routes').'/api.php',
        ]);

        // Merge configs with the default configs
        $this->mergeConfigFrom(
            __DIR__.'/../publish/config/azure-oath.php', 'azure-oath'
        );

        // Load our HTTP routes for API and WEB authentication
        $this->loadRoutesFrom(__DIR__.'/../routes/api.microsoft.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.microsoft.php');

        // Trigger generating our swagger oauth security settings based on application env file contents
        $this->generateSwaggerOauthSecurityScheme();
    }

    protected function generateSwaggerOauthSecurityScheme()
    {
        // If the routes files for the swagger oauth config is NOT present, and we have all the right info, then generate it really quick
        $swaggerAzureadFile = __DIR__.'/../routes/swagger.azuread.php';
        if (! file_exists($swaggerAzureadFile)) {
            $aad = new AzureActiveDirectory(env('AZURE_AD_TENANT'));
            //$authorizationUrl = $aad->authorizationEndpoint . '?resource=https://graph.microsoft.com';
            $authorizationUrl = $aad->authorizationEndpoint;
            $client_id = env('AZURE_AD_CLIENT_ID');
            $contents = <<<EOF
<?php
/**
 * @SWG\SecurityScheme(
 *   securityDefinition="AzureAD",
 *   type="oauth2",
 *   authorizationUrl="$authorizationUrl",
 *   flow="implicit",
 *   scopes={
 *       "https://graph.microsoft.com/.default": "Use client_id: $client_id"
 *   }
 * )
 **/
EOF;
            file_put_contents($swaggerAzureadFile, $contents);
        }
    }

}
