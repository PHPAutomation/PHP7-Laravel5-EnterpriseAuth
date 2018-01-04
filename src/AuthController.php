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

        $authUser = $this->findOrCreateUser($user);

        auth()->login($authUser, true);

        session([
            'azure_access_token' => $user->token,
            'azure_id_token' => $user->id_token
        ]);

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

        $id_field = config('azure-oath.user_id_field');

        $new_user = new $user_class;
        $new_user->name = $user->name;
        $new_user->email = $user->email;
        $new_user->$id_field = $user->id;
        $new_user->save();

        return $new_user;
    }
}
