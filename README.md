# Laravel Socialite Azure Active Directory Plugin

## Installation

`composer require metaclassing/php7-laravel5-enterpriseauth`

Publish the config and override any defaults:

```
php artisan vendor publish --tag 'i will decide what to put here later'
```

Add the necessary env vars for Azure Active Directory OAUTH:

```
AZURE_AD_CLIENT_ID="1234abcd-12ab-34cd-56ef-123456abcdef"
AZURE_AD_CLIENT_SECRET="123456789abcdef123456789abcdef\123456789abc="
AZURE_AD_CALLBACK_URL="https://myapp.mycompany.com/login/microsoft/callback"
^--- this one I will remove once I get the route named something sane.
```

I have a published migration that needs to run altering the user table:
  user password is nullable()
  user has an azure_id string(36) attribute




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
