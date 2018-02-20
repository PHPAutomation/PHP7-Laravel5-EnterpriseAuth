# Laravel Socialite Azure Active Directory Plugin

## PRE INSTALLATION

Make sure you dont have any outstanding migrations, this assumes you are installing from a FRESH laravel 5.5 project
```
composer create-project --prefer-dist laravel/laravel laravel55 "5.5.*"
cd laravel55
# EDIT YOUR .ENV FILE for things like database connection creds etc.
php artisan migrate
# make sure your permissions are correct so the app works
chown -R www-data .

```

## Installation

This is a dev package, your minimum stability must support this:
```
composer config minimum-stability dev
composer config prefer-stable true
composer require metaclassing/php7-laravel5-enterpriseauth
```

Publish the config and override any defaults:

```
# Metrogistics AzureSocialite lib - currently base of this fork
php artisan vendor:publish --provider="Metrogistics\AzureSocialite\ServiceProvider"
php artisan migrate

# JWT Authentication lib - currently running dev branch for 5.5 support
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret

# Bouncer Authorization lib
php artisan vendor:publish --tag="bouncer.migrations"
php artisan migrate
```

Add the necessary env vars for Azure Active Directory OAUTH:

```
AZURE_AD_CLIENT_ID="1234abcd-12ab-34cd-56ef-123456abcdef"
AZURE_AD_CLIENT_SECRET="123456789abcdef123456789abcdef\123456789abc="
AZURE_AD_CALLBACK_URL="https://myapp.mycompany.com/login/microsoft/callback"
^--- this one I will remove once I get the route named something sane.
```




## Cookie thick browser client usage

All you need to do to make use of Azure AD SSO is to point a user to the `/login/microsoft` route (configurable) for login. Once a user has been logged in, they will be redirect to the home page (also configurable).

After login, you can access the basic Laravel authenticate user as normal:

```
auth()->user();
```

If you need to set additional user fields when the user model is created at login, you may provide a callback via the `UserFactory::userCallback()` method. A good place to do so would be in your AppServiceProvider's `boot` method:

```
\Metrogistics\AzureSocialite\UserFactory::userCallback(function($new_user){
	$new_user->api_token = str_random(60);
});
```

## Azure AD Setup

TL;DR - Run the runbook in azure that creates a new aad app with unlimited key timeout and access to view user groups.

Manual setup instructions:

1. Navigate to `Azure Active Directory` -> `App registrations`.
2. Create a new application
  1. Choose a name
  2. Select the "Web app / API" Application Type
  3. Add the "Sign-on URL". This will typically be `https://domain.com/auth/login`
  4. Click "Create"
3. Click into the newly created app.
4. The "Application ID" is what you will need for your `AZURE_AD_CLIENT_ID` env variable.
5. Click into "Reply URLs". You will need to whitelist the redirection path for your app here. It will typically be `https://domain.com/login/microsoft/callback`. Click "Save"
6. Select the permissions required for you app in the "Required permissions" tab.
8. In the "Keys" tab, enter a description (something like "App Secret"). Set Duration to "Never Expires". Click "Save". Copy the whole key. This will not show again. You will need this value for the `AZURE_AD_CLIENT_SECRET` env variable.
over9000: there are some steps here missing to pre-authorize user access to the app and what permissions it has without prompting.
