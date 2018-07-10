# PHP7-Laravel5-EnterpriseAuth for Azure Active Directory
[![Build Status](https://scrutinizer-ci.com/g/metaclassing/PHP7-Laravel5-EnterpriseAuth/badges/build.png?b=master)](https://scrutinizer-ci.com/g/metaclassing/PHP7-Laravel5-EnterpriseAuth/build-status/master)
[![Style-CI](https://styleci.io/repos/122122106/shield?branch=master)](https://styleci.io/repos/122122106)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/metaclassing/PHP7-Laravel5-EnterpriseAuth/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/metaclassing/PHP7-Laravel5-EnterpriseAuth/?branch=master)

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

Add the necessary env vars for Azure Active Directory OAUTH:

```
AZURE_AD_TENANT="MyAwesomeAzureADTenant"
AZURE_AD_CLIENT_ID="1234abcd-12ab-34cd-56ef-123456abcdef"
AZURE_AD_CLIENT_SECRET="123456789abcdef123456789abcdef\123456789abc="
AZURE_AD_CALLBACK_URL="https://myapp.mycompany.com/login/microsoft/callback"
# ^--- this is the library callback for session based auth. you could use /ui/ for a single-page-app
```

This is a dev package, your minimum stability must support this:
```
composer config minimum-stability dev
composer config prefer-stable true
composer require metaclassing/php7-laravel5-enterpriseauth
```

Publish the config and override any defaults:

```
# Metaclassing\EnterpriseAuth is this library
php artisan vendor:publish --provider="Metaclassing\EnterpriseAuth\ServiceProvider" --force
php artisan migrate

# JWT Authentication lib - currently running dev branch for 5.5 support
#php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
#php artisan jwt:secret

# Bouncer Authorization lib
php artisan vendor:publish --tag="bouncer.migrations"
php artisan migrate

# OwenIt Auditing
php artisan vendor:publish --provider="OwenIt\Auditing\AuditingServiceProvider"
php artisan auditing:install
php artisan migrate

# L5-Swagger api documentation
php artisan l5-swagger:generate
```

Double check your permissions are golden!

```
chown -R www-data .
```


## Bouncer group-based authorization
By default when a user authenticates their group information is populated into the bouncer roles list using group display name properties.
Quick shortcuts to grant permissions to roles(groups) based on model type or instance
```
// ROLES (group display name in AD)
$roles = [
             'Enterprise.Architecture',
             'IMTelecom',
         ];

// TYPES of things (all instances)
$types = [
             App\Thing::class,
             App\OtherThing::class,
         ];

// PERMISSIONS the role can do to the type of thing, this goes in your controller
$tasks = [
             "create",
             "read",
             "update",
             "delete",
             "suckit",
         ];

// Let those roles/groups do tasks to things.
foreach($roles as $role) {
    foreach($types as $type) {
        foreach($tasks as $task) {
            Bouncer::allow($role)->to($task, $type);
        }
    }
}
```

If you want to do SPECIFIC INSTANCES of an object rather than ALL of type X
```
// TYPES of things (all instances)
$stuff = [
             \App\Thing::find(2),
             \App\OtherThing::find(16),
         ];

// Let those roles/groups do tasks to SPECIFIC INSTANCES of things.
foreach($roles as $role) {
    foreach($stuff as $thing) {
        foreach($tasks as $task) {
            Bouncer::allow($role)->to($task, $thing);
        }
   }
}
```
In your controller you will need to ensure your user is authenticated, and then check if they can do 'permission' to typeOfModel::class OR $instanceOfModel
```
    public function myHttpControllerRandomApiFunction(Request $request)
    {
        // authenticate the user
        $user = auth()->user();

        // permission check on specific $thing
        $thing = \App\Crud::find(123);
        if ($user->cant('suckit', $thing)) {
            return response()->json(['error' => 'user cant suck this'], 401);
        }

        // permission check on all things of typeOfModel
        if ($user->cant('suckit', \App\CrudModel::class)) {
            return response()->json(['error' => 'user cant suck this'], 401);
        }

        // suck it.
        $thing->suck('it');

        // send some response
        return response()->json($roles);
    }
```

## Cookie thick browser client usage

All you need to do to make use of Azure AD SSO is to point a user to the `/login/microsoft` route (configurable) for login. Once a user has been logged in, they will be redirect to the home page (also configurable).

After login, you can access the basic Laravel authenticate user as normal:

```
auth()->user();
```

## Azure AD Application Registration
1. Goto https://apps.dev.microsoft.com and create a new app.
2. Create a new application secert (generate password) and save that with the app-id in your .env file
3. Create a new Web platform with the following redirect URL's:
    * https://myapp.mycompany.com/login/microsoft/callback (For thick-cookie-session browser login)
    * https://myapp.mycompany.com/api/oauth2-callback (For swagger UI API docs login)
4. Set the logout url if desired: https://myapp.mycompany.com/logout
5. If you are doing app-to-app authentication, you may need a web API platform. The default access_as_user scope is fine for any client applications you authorize
6. Default user permissions of user.read are fine, dont change anything
7. Add application permission directory.read.all permission (admin only) is required if you want to see user group information
8. To gain the authorization you need your azure AD admin to visit https://myapp.mycompany.com/login/microsoft/adminconsent and click ok.
9. Dont forget to click save on everything.
