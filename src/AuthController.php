<?php

namespace Metrogistics\AzureSocialite;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{

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

    public function loginOrRegister (\Illuminate\Http\Request $request)
    {
        return $request->expectsJson()
               ? response()->json(['message' => $exception->getMessage()], 401)
               : redirect()->guest(config('azure-oath.routes.login'));
    }

}
