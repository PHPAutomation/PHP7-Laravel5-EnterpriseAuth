<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToOauthProvider()
    {
        return Socialite::driver('azure-oauth')->redirect();
    }

    public function handleOauthResponse()
    {
        $user = Socialite::driver('azure-oauth')->user();
        //dd($user);

        $authUser = $this->findOrCreateUser($user);

        // If we have user group information from this oauth attempt
        if(count($user->groups)) {
            // remove the users existing database roles before assigning new ones
            $oldroles = $authUser->roles()->get();
            foreach ($oldroles as $role) {
                $authUser->retract($role);
            }
            // add the user to each group they are assigned
            $newroles = $user->groups;
            foreach ($newroles as $role) {
                $authUser->assign($role);
            }
        }

        auth()->login($authUser, true);

        // session([
        //     'azure_user' => $user
        // ]);

        return redirect(
            config('azure-oath.redirect_on_login')
        );
    }

    protected function findOrCreateUser($user)
    {
        $user_class = config('azure-oath.user_class');
        $authUser = $user_class::where(config('azure-oath.user_id_field'), $user->id)->first();

        if ($authUser) {
            return $authUser;
        }

        $UserFactory = new UserFactory();

        return $UserFactory->convertAzureUser($user);
    }

    protected function updateUserGroups($user)
    {
        dd($user);
    }
}
