<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class WebAuthController extends AuthController
{
    public function redirectToOauthProvider()
    {
        return Socialite::driver('azure-oauth')->redirect();
    }

    public function handleOauthResponse(\Illuminate\Http\Request $request)
    {
        $user = Socialite::driver('azure-oauth')->user();
        //dd($user);

        $authUser = $this->findOrCreateUser($user);

        // If we have user group information from this oauth attempt
        if (count($user->groups)) {
            // remove the users existing database roles before assigning new ones
            \DB::table('assigned_roles')
               ->where('entity_id', $authUser->id)
               ->where('entity_type', get_class($authUser))
               ->delete();
            // add the user to each group they are assigned
            $authUser->assign($user->groups);
        }

        auth()->login($authUser, true);

        // session([
        //     'azure_user' => $user
        // ]);

        // Check to see if there is an intended destination url saved
        $destination = $request->session()
                               ->get('oauthIntendedUrl');
        // If there is no intended destination url, use the default
        if(! $destination) {
            $destination = config('azure-oath.redirect_on_login');
        }
        \Illuminate\Support\Facades\Log::info('AUTH success USER ID '.$authUser->id.' with redirect url '.$destination);
        return redirect($destination);
    }
}
