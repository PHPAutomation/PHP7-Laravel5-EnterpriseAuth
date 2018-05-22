<?php

namespace Metaclassing\EnterpriseAuth;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        // Make sure nobody is including or running this thing without all the required env settings
        $this->checkMandatoryConfigsAreSet();

        // Install our API auth guard middleware
        $this->installOauthTokenGuardMiddleware();

        // Make sure that this vendor dir and the routes dir are in any scanned paths for swagger documentation
        $this->configureSwaggerToScanEnterpriseAuthRouteFiles();

        // Make sure the publish command picks up our config, migration, user model, and dummy API route files
        $this->publishes([
            __DIR__.'/../publish/config/enterpriseauth.php'                                                => config_path('enterpriseauth.php'),
            __DIR__.'/../publish/database/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php' => $this->app->databasePath().'/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php',
            __DIR__.'/../publish/app/User.php'                                                             => app_path().'/User.php',
            __DIR__.'/../publish/routes/api.php'                                                           => base_path('routes').'/api.php',
        ]);

        // Merge configs with the default configs
        $this->mergeConfigFrom(
            __DIR__.'/../publish/config/enterpriseauth.php', 'enterpriseauth'
        );

        // Load our HTTP routes for API and WEB authentication
        $this->loadRoutesFrom(__DIR__.'/../routes/api.microsoft.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.microsoft.php');

        // Trigger generating our swagger oauth security settings based on application env file contents
        $this->generateSwaggerOauthSecurityScheme();
    }

    protected function checkMandatoryConfigsAreSet()
    {
        // On first run this will be false, after config file is installed it will be true
        if (config('enterpriseauth')) {
            // Go through all the credential config and make sure they are set in the .env or config file
            foreach (config('enterpriseauth.credentials') as $config => $env) {
                // If one isnt set, throw a red flat until the person fixes it
                if (! config('enterpriseauth.credentials.'.$config)) {
                    throw new \Exception('enterpriseauth setup error: missing mandatory config value for enterpriseauth.credentials.'.$config.' check your .env file!');
                }
            }
        }
    }

    protected function installOauthTokenGuardMiddleware()
    {
        // Override the application configuration to use our oauth token guard driver at runtime
        config(['auth.guards.api.driver' => 'oauthtoken']);
        // Now I have a machine gun. ho ho ho!
        \Illuminate\Support\Facades\Auth::extend('oauthtoken', function ($app, $name, array $config) {
            $userProvider = \Illuminate\Support\Facades\Auth::createUserProvider($config['provider']);

            return new \Metaclassing\EnterpriseAuth\Middleware\OauthTokenGuard($userProvider, $app->make('request'));
        });
    }

    protected function configureSwaggerToScanEnterpriseAuthRouteFiles()
    {
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
    }

    protected function generateSwaggerOauthSecurityScheme()
    {
        // If the routes files for the swagger oauth config is NOT present, and we have all the right info, then generate it really quick
        $swaggerAzureadFile = __DIR__.'/../routes/swagger.azuread.php';
        if (! file_exists($swaggerAzureadFile)) {
            $aad = new AzureActiveDirectory(config('enterpriseauth.credentials.tenant'));
            //$authorizationUrl = $aad->authorizationEndpoint . '?resource=https://graph.microsoft.com';
            $authorizationUrl = $aad->authorizationEndpoint;
            $client_id = config('enterpriseauth.credentials.client_id');
            $contents = <<<EOF
<?php
/**
 * @SWG\SecurityScheme(
 *   securityDefinition="AzureAD",
 *   type="oauth2",
 *   authorizationUrl="$authorizationUrl",
 *   flow="implicit",
 *   scopes={
 *       "api://$client_id/.default": "Use client_id: $client_id",
 *   }
 * )
 **/
EOF;
            // *       "https://graph.microsoft.com/.default": "Use client_id: $client_id"
            file_put_contents($swaggerAzureadFile, $contents);
        }
    }
}
