<?php

namespace Metrogistic\AzureSocialite;

use SocialiteProviders\Manager\SocialiteWasCalled;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {}

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/azure-oath.php' => config_path('azure-oath.php'),
        ]);

        $this->mergeConfigFrom(
            __DIR__.'/config/azure-oath.php', 'azure-oath'
        );

        $this->app['events']->listen(SocialiteWasCalled::class, function (SocialiteWasCalled $socialiteWasCalled) {
            $socialiteWasCalled->extendSocialite(
                'azure-oauth', __NAMESPACE__.'\AzureOauthProvider'
            );
        });

        $this->app['router']->get(config('azure-oath.routes.login'), function(){

        });

        $this->app['router']->get(config('azure-oath.routes.callback'), function(){

        });
    }
}
