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
        // Auth::extend('azure', function(){
        //     dd('test');
        //     return new Authenticate();
        // });

        /*
        $userModelFile = app_path().'/User.php';
        $userModelData = file_get_contents($userModelFile);
        $userModelHash = md5($userModelData);
        // ONLY REPLACE THE ORIGINAL DEFAULT User.php file, dont replace it multiple times!
        if ($userModelHash == '15f19dad7b287f9204dbe2b34dd424d7') {
            try {
                \Log::debug('Detected default User.php model, replacing with enhanced model...');
                copy(__DIR__.'/models/User.php', $userModelFile);
                \Log::debug('Default User.php model replaced successfully');
            } catch (\Throwable $e) {
                \Log::debug('Detected default User.php model but replacement failed, '.$e->getMessage());
            }
        }
        */

        // change the api auth guard to jwt rather than default of token
        config(['auth.guards.api.driver' => 'jwt']);
        //dd(config('auth.guards.api'));

        // Make sure that this vendor dir and the routes dir are in any scanned paths for swagger documentation
        $swaggerScanPaths = config('l5-swagger.paths.annotations');
        if(! is_array($swaggerScanPaths)) {
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
            __DIR__.'/../publish/config/azure-oath.php' => config_path('azure-oath.php'),
            __DIR__.'/../publish/database/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php' => $this->app->databasePath().'/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php',
            __DIR__.'/../publish/app/User.php' => app_path().'/User.php',
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/../publish/config/azure-oath.php', 'azure-oath'
        );

        $this->app['Laravel\Socialite\Contracts\Factory']->extend('azure-oauth', function($app){
            return $app['Laravel\Socialite\Contracts\Factory']->buildProvider(
                'Metrogistics\AzureSocialite\AzureOauthProvider',
                config('azure-oath.credentials')
            );
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/api.microsoft.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.microsoft.php');
    }
}
