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

        $userModelFile = app_path().'/User.php';
        $userModelData = file_get_contents($userModelFile);
        $userModelHash = md5($userModelData);
        // ONLY REPLACE THE ACTUAL DEFAULT User.php file, dont replace it multiple times!
        if ($userModelHash == '15f19dad7b287f9204dbe2b34dd424d7') {
            unlink($userModelFile);
        }

        // change the api auth guard to jwt rather than default of token
        //config(['auth.guards.api.driver' => 'jwt']);
        //dd(config('auth.guards.api'));

        $this->publishes([
            __DIR__.'/config/azure-oath.php' => config_path('azure-oath.php'),
            __DIR__.'/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php' => $this->app->databasePath().'/migrations/2018_02_19_152839_alter_users_table_for_azure_ad.php',
            __DIR__.'/models/User.php' => $userModelFile,
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/config/azure-oath.php', 'azure-oath'
        );

        $this->app['Laravel\Socialite\Contracts\Factory']->extend('azure-oauth', function($app){
            return $app['Laravel\Socialite\Contracts\Factory']->buildProvider(
                'Metrogistics\AzureSocialite\AzureOauthProvider',
                config('azure-oath.credentials')
            );
        });

        $this->app['router']->group(['middleware' => config('azure-oath.routes.middleware')], function($router){
            $router->get(config('azure-oath.routes.login'), 'Metrogistics\AzureSocialite\AuthController@redirectToOauthProvider');
            $router->get(config('azure-oath.routes.callback'), 'Metrogistics\AzureSocialite\AuthController@handleOauthResponse');
            $router->get(config('azure-oath.routes.apilogin'), 'Metrogistics\AzureSocialite\AuthController@handleApiOauthLogin');
            $router->post(config('azure-oath.routes.apilogin'), 'Metrogistics\AzureSocialite\AuthController@handleApiOauthLogin');
            $router->get('/api/me/', 'Metrogistics\AzureSocialite\AuthController@getAuthorizedUserInfo');
            $router->get('/api/me/roles', 'Metrogistics\AzureSocialite\AuthController@getAuthorizedUserRoles');
        });

        // Unauthenticated api route
        $this->app['router']->group(['middleware' => config('azure-oath.apiroutes.middleware')], function($router){
            $router->get(config('azure-oath.apiroutes.login'), 'Metrogistics\AzureSocialite\AuthController@handleApiOauthLogin');
            $router->post(config('azure-oath.apiroutes.login'), 'Metrogistics\AzureSocialite\AuthController@handleApiOauthLogin');
        });
        $this->app['router']->group(['middleware' => [ config('azure-oath.apiroutes.middleware'), config('azure-oath.apiroutes.authmiddleware') ] ], function($router){
            $router->get(config('azure-oath.apiroutes.myinfo'), 'Metrogistics\AzureSocialite\AuthController@getAuthorizedUserInfo');
            $router->get(config('azure-oath.apiroutes.myroles'), 'Metrogistics\AzureSocialite\AuthController@getAuthorizedUserRoles');
        });

    }
}
